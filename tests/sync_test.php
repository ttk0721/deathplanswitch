<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Clock, Cluster, Coordinator, Engine, Node, NodeApi, Outbox, PeerClient, Store, Sync, Transport, Email};
require __DIR__ . '/lib.php';

final class NullMail implements Transport
{
    public function send(array $smtp, array $to, string $fromName, string $replyTo, Email $email): void {}
}

function b64(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}
function nd(string $id, int $rank, string $url, ?string $pk = null): array
{
    return ['id' => $id, 'name' => 'Node ' . $id, 'url' => $url, 'sign_pk' => $pk ?? b64(str_repeat('a', 32)),
        'share_pk' => 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE', 'rank' => $rank];
}

/** In-memory network. $routes[host][a] = fn(string $method, array $query, string $body): string|array, or throws. */
final class Net
{
    public array $routes = [];
    public array $calls = [];
    /** host => [node id, Ed25519 secret key]; answers are signed as that node. */
    public array $keys = [];
    /** fn(string $host, string $a, array $answer): array may change the answer after it is signed. */
    public ?\Closure $tamper = null;
    public function __invoke(string $method, string $url, array $headers, string $body, int $timeout = 20): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $a = (string) ($q['a'] ?? '');
        unset($q['a']);
        $this->calls[] = ['host' => $host, 'method' => $method, 'a' => $a, 'query' => $q, 'body' => $body];
        $fn = $this->routes[$host][$a] ?? null;
        if ($fn === null) {
            return [404, '{"error":"not found"}', []];
        }
        $out = $fn($method, $q, $body);
        $raw = is_array($out) ? json_encode($out) : $out;
        $nonce = '';
        foreach ($headers as $h) {
            if (str_starts_with($h, 'X-DPS-Nonce: ')) {
                $nonce = substr($h, 13);
            }
        }
        [$id, $sk] = $this->keys[$host];
        $answer = [200, $raw, ['x-dps-node' => $id,
            'x-dps-signature' => b64(sodium_crypto_sign_detached(NodeApi::responseCanonical($nonce, 200, hash('sha256', $raw)), $sk))]];
        return $this->tamper === null ? $answer : ($this->tamper)($host, $a, $answer);
    }
    public function count(string $host, string $a): int
    {
        return count(array_filter($this->calls, static fn (array $c): bool => $c['host'] === $host && $c['a'] === $a));
    }
}

final class SW
{
    public Store $store;
    public Net $net;
    public Engine $engine;
    public Sync $sync;
    public string $id;
    public const P1 = 'n_11111111';
    public const P2 = 'n_22222222';

    /** @param string|null $primary node id of the primary, null = no primary */
    public function __construct(?string $primary, bool $two = true)
    {
        $dir = sys_get_temp_dir() . '/dps-sync-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        Clock::set(1_800_000_000);
        $this->store = new Store($dir);
        $this->id = Node::init($this->store, 'Home')['id'];
        $self = Node::publicIdentity($this->store);
        $this->net = new Net();
        $peerNode = function (string $id, int $rank, string $host): array {
            $kp = sodium_crypto_sign_keypair();
            $this->net->keys[$host] = [$id, sodium_crypto_sign_secretkey($kp)];
            return nd($id, $rank, 'https://' . $host, b64(sodium_crypto_sign_publickey($kp)));
        };
        $nodes = [nd($this->id, 2, 'https://self.example.org', $self['sign_pk']), $peerNode(self::P1, 0, 'p1.example.org')];
        if ($two) {
            $nodes[] = $peerNode(self::P2, 1, 'p2.example.org');
        }
        Cluster::save($this->store, ['k' => 1, 'primary' => $primary, 'isolation_hours' => 48, 'step_delay_min' => 30, 'nodes' => $nodes]);
        $this->store->write('config', ['owner_name' => 'Local', 'base_url' => 'https://local.example.org', 'tick_key' => 'ltk',
            'health_key' => 'lhk', 'installed_at' => 111]);
        $coord = new Coordinator($this->store, $this->id);
        $this->engine = new Engine($this->store, new Outbox($this->store, new NullMail()), null, $coord);
        $this->sync = new Sync($this->store, $this->engine, new PeerClient($this->store, $this->net), $coord, $this->id);
    }

    public function peerState(string $id, array $more = []): array
    {
        return $more + ['node' => $id, 'at' => Clock::now(), 'last_checkin_at' => null, 'last_checkin_via' => null,
            'armed' => true, 'released' => false, 'cycle' => null];
    }

    public function answerStates(): void
    {
        $this->net->routes['p1.example.org']['state'] = fn () => $this->peerState(self::P1);
        $this->net->routes['p2.example.org']['state'] = fn () => $this->peerState(self::P2);
    }
}

test('peer states are recorded', function (): void {
    $w = new SW(null);
    $w->answerStates();
    $log = $w->sync->run();
    $peers = $w->store->read('peers');
    same([SW::P1, SW::P2], array_keys($peers), 'both recorded');
    same(1_800_000_000, $peers[SW::P1]['seen_at'], 'seen_at');
    same(SW::P1, $peers[SW::P1]['state']['node'], 'state kept');
    check(is_array($log), 'log lines');
});

test('a local check-in is pushed once, not again', function (): void {
    $w = new SW(null);
    $w->answerStates();
    foreach (['p1.example.org', 'p2.example.org'] as $h) {
        $w->net->routes[$h]['checkin'] = fn () => ['ok' => true];
    }
    $s = $w->engine->state();
    $s['last_checkin_at'] = 1_799_999_000;
    $s['last_checkin_via'] = 'the emailed link';
    $w->engine->saveState($s);
    $w->sync->run();
    same(1, $w->net->count('p1.example.org', 'checkin'), 'p1 pushed');
    same(1, $w->net->count('p2.example.org', 'checkin'), 'p2 pushed');
    $body = json_decode($w->net->calls[array_key_last(array_filter($w->net->calls, fn ($c) => $c['a'] === 'checkin'))]['body'], true);
    same(1_799_999_000, $body['at'], 'at');
    same('the emailed link', $body['via'], 'via');
    $w->sync->run();
    same(1, $w->net->count('p1.example.org', 'checkin'), 'not pushed again');
    same(1_799_999_000, $w->store->read('sync')['pushed_checkin_at'], 'sync.json');
});

test('an "all fine" is pushed with the key of the cycle it was given in', function (): void {
    $w = new SW(null);
    $w->answerStates();
    foreach (['p1.example.org', 'p2.example.org'] as $h) {
        $w->net->routes[$h]['nok'] = fn () => ['ok' => true];
    }
    $w->store->log('cycle.vouched', 'Lee answered "All fine". Nobody else will be asked.', ['key' => 1_799_000_000, 'by' => 'c_0123456789']);
    $w->sync->run();
    $calls = array_values(array_filter($w->net->calls, fn ($c) => $c['a'] === 'nok'));
    same(2, count($calls), 'pushed to both peers');
    $body = json_decode($calls[0]['body'], true);
    same(['answer' => 'fine', 'key' => 1_799_000_000, 'by' => 'c_0123456789'], ['answer' => $body['answer'], 'key' => $body['key'] ?? null, 'by' => $body['by'] ?? null], 'with its cycle key and who answered');
});

test('a check-in that came from another node is not echoed back', function (): void {
    $w = new SW(null);
    $w->answerStates();
    $w->net->routes['p1.example.org']['checkin'] = fn () => ['ok' => true];
    $s = $w->engine->state();
    $s['last_checkin_at'] = 1_799_999_000;
    $s['last_checkin_via'] = 'another node';
    $w->engine->saveState($s);
    $w->sync->run();
    same(0, $w->net->count('p1.example.org', 'checkin'), 'nothing pushed');
});

function bundleFrom(SW $w, int $version): array
{
    $cluster = Cluster::read($w->store);
    return ['version' => $version,
        'config' => ['owner_name' => 'Primary Owner', 'base_url' => 'https://primary.example.org', 'tick_key' => 'ptk', 'health_key' => 'phk', 'installed_at' => 5, 'timezone' => 'UTC'],
        'contacts' => ['contacts' => [['id' => 'c_1', 'name' => 'Kim']]],
        'vault' => ['packages' => [], 'recipients' => []],
        'cluster' => $cluster];
}

/** A vault package that passes Vault's checks. */
function pkg(string $fid, int $chunks): array
{
    $sealed = ['iv' => str_repeat('A', 16), 'ct' => 'AAAA'];
    return ['id' => 'p_0123456789abcdef', 'label' => 'Papers', 'recipients' => [], 'owner_wrapped_ck' => $sealed,
        'wraps' => [], 'manifest' => null, 'files' => [['id' => $fid, 'chunks' => $chunks, 'size' => 10]]];
}

test('a bundle is applied and the local keys survive', function (): void {
    $w = new SW(SW::P1);
    $w->answerStates();
    $w->net->routes['p1.example.org']['bundle'] = fn () => bundleFrom($w, 777);
    $w->sync->run();
    $c = $w->store->read('config');
    same('Primary Owner', $c['owner_name'], 'taken from primary');
    same('UTC', $c['timezone'], 'timezone');
    same('https://local.example.org', $c['base_url'], 'base_url kept');
    same('ltk', $c['tick_key'], 'tick_key kept');
    same('lhk', $c['health_key'], 'health_key kept');
    same(111, $c['installed_at'], 'installed_at kept');
    same('Kim', $w->store->read('contacts')['contacts'][0]['name'], 'contacts');
    same(777, $w->store->read('sync')['bundle_version'], 'version stored');
    check(Node::publicIdentity($w->store) !== null, 'node.json untouched');
});

test('an unchanged bundle is skipped', function (): void {
    $w = new SW(SW::P1);
    $w->answerStates();
    $w->net->routes['p1.example.org']['bundle'] = fn () => bundleFrom($w, 777);
    $w->sync->run();
    $w->store->write('config', ['owner_name' => 'Edited locally', 'base_url' => 'x', 'tick_key' => 'ltk', 'health_key' => 'lhk', 'installed_at' => 111]);
    $w->net->routes['p1.example.org']['bundle'] = fn (string $m, array $q) => ['unchanged' => true, 'since' => $q['since'] ?? null];
    $w->sync->run();
    $last = array_values(array_filter($w->net->calls, fn ($c) => $c['a'] === 'bundle'));
    same('777', $last[1]['query']['since'], 'since sent');
    same('Edited locally', $w->store->read('config')['owner_name'], 'not overwritten');
});

test('the primary never asks for a bundle', function (): void {
    $w = new SW(null);
    $w->answerStates();
    $w->sync->run();
    same(0, $w->net->count('p1.example.org', 'bundle'), 'no bundle call');
});

test('a missing blob is downloaded, an existing one is not', function (): void {
    $w = new SW(SW::P1);
    $w->answerStates();
    $fid = 'f_0123456789abcdef';
    $b = bundleFrom($w, 5);
    $b['vault'] = ['recipients' => [], 'packages' => [pkg($fid, 2)]];
    $w->net->routes['p1.example.org']['bundle'] = fn () => $b;
    $w->net->routes['p1.example.org']['blob'] = fn (string $m, array $q) => 'BYTES-' . $q['n'];
    $dir = $w->store->dir() . '/vault/' . $fid;
    mkdir($dir, 0700, true);
    file_put_contents($dir . '/0.bin', 'old');
    $w->sync->run();
    same(1, $w->net->count('p1.example.org', 'blob'), 'one download');
    same('BYTES-1', file_get_contents($dir . '/1.bin'), 'chunk 1 stored');
    same('old', file_get_contents($dir . '/0.bin'), 'chunk 0 kept');
    $w->sync->run();
    same(1, $w->net->count('p1.example.org', 'blob'), 'nothing more to download');
});

test('an exhausted budget stops the downloads', function (): void {
    $w = new SW(SW::P1);
    $w->answerStates();
    $fid = 'f_0123456789abcdef';
    $b = bundleFrom($w, 5);
    $b['vault'] = ['recipients' => [], 'packages' => [pkg($fid, 3)]];
    $w->net->routes['p1.example.org']['bundle'] = fn () => $b;
    $w->net->routes['p1.example.org']['blob'] = fn () => 'X';
    $w->sync->run(0);
    same(0, $w->net->count('p1.example.org', 'blob'), 'no time, no downloads');
});

test('a failing peer is logged and the others are still done', function (): void {
    $w = new SW(null);
    $w->net->routes['p1.example.org']['state'] = function (): never { throw new \RuntimeException('connection refused'); };
    $w->net->routes['p2.example.org']['state'] = fn () => $w->peerState(SW::P2);
    $log = $w->sync->run();
    same([SW::P2], array_keys($w->store->read('peers')), 'p2 recorded');
    check(count(array_filter($log, fn ($l) => str_contains($l, 'Node ' . SW::P1) && str_contains($l, 'connection refused'))) === 1, 'p1 failure logged');
});

test('a state that claims to be another node is ignored', function (): void {
    $w = new SW(null);
    $w->net->routes['p1.example.org']['state'] = fn () => $w->peerState('n_99999999');
    $w->sync->run();
    same([], array_keys($w->store->read('peers')), 'nothing recorded');
});

test('a bundle whose signature does not match is never applied', function (): void {
    $w = new SW(SW::P1);
    $w->answerStates();
    $w->net->routes['p1.example.org']['bundle'] = fn () => bundleFrom($w, 777);
    $w->net->tamper = function (string $host, string $a, array $r): array {
        if ($a === 'bundle') {
            $r[1] = str_replace('Primary Owner', 'Attacker', $r[1]);
        }
        return $r;
    };
    $log = $w->sync->run();
    same('Local', $w->store->read('config')['owner_name'], 'config untouched');
    same([], $w->store->read('contacts'), 'contacts untouched');
    check(!isset($w->store->read('sync')['bundle_version']), 'no version stored');
    check((bool) array_filter($log, fn ($l) => str_contains($l, 'bad response signature')), 'logged');
});

test('a state or blob whose signature does not match is not used', function (): void {
    $w = new SW(SW::P1);
    $w->answerStates();
    $fid = 'f_0123456789abcdef';
    $b = bundleFrom($w, 5);
    $b['vault'] = ['recipients' => [], 'packages' => [pkg($fid, 1)]];
    $w->net->routes['p1.example.org']['bundle'] = fn () => $b;
    $w->net->routes['p1.example.org']['blob'] = fn () => 'BYTES';
    $w->net->tamper = function (string $host, string $a, array $r): array {
        if ($a === 'blob' || ($a === 'state' && $host === 'p2.example.org')) {
            $r[1] = $a === 'blob' ? 'EVIL!' : str_replace('"last_checkin_at":null', '"last_checkin_at":1799999999', $r[1]);
        }
        return $r;
    };
    $w->sync->run();
    same([SW::P1], array_keys($w->store->read('peers')), 'the forged state is not recorded');
    $dir = $w->store->dir() . '/vault/' . $fid;
    check(!is_file("$dir/0.bin") && !is_file("$dir/0.bin.part"), 'no forged blob on disk');
});

test('bundle schema: unknown config keys are dropped, bad contacts or vault refuse the bundle', function (): void {
    $w = new SW(SW::P1);
    $w->answerStates();
    $b = bundleFrom($w, 7);
    $b['config']['evil_key'] = 'x';
    $b['vault'] = ['version' => 1, 'owner' => null, 'recipients' => [], 'packages' => [pkg('f_0123456789abcdef', 1) + ['extra' => 'x']], 'junk' => 1];
    $w->net->routes['p1.example.org']['bundle'] = fn () => $b;
    $w->net->routes['p1.example.org']['blob'] = fn () => 'B';
    $w->sync->run();
    $c = $w->store->read('config');
    check(!array_key_exists('evil_key', $c) && $c['owner_name'] === 'Primary Owner', 'unknown key dropped, known kept');
    $v = $w->store->read('vault');
    same(['version', 'owner', 'recipients', 'packages'], array_keys($v), 'vault shape');
    check(!isset($v['packages'][0]['extra']) && $v['packages'][0]['label'] === 'Papers', 'package cleaned by Vault');

    foreach ([
        'contacts not a list' => fn (array $x): array => ['contacts' => $x['contacts']['contacts'][0]] + $x,
        'contact not an object' => fn (array $x): array => ['contacts' => ['contacts' => ['Kim']]] + $x,
        'contacts key missing' => fn (array $x): array => ['contacts' => ['people' => []]] + $x,
        'bad package' => fn (array $x): array => ['vault' => ['recipients' => [], 'packages' => [['id' => 'p_0123456789abcdef', 'files' => []]]]] + $x,
        'bad recipient' => fn (array $x): array => ['vault' => ['recipients' => [['id' => '../x']], 'packages' => []]] + $x,
    ] as $why => $mutate) {
        $w2 = new SW(SW::P1);
        $w2->answerStates();
        $bad = $mutate(bundleFrom($w2, 9));
        $w2->net->routes['p1.example.org']['bundle'] = fn () => $bad;
        $log = $w2->sync->run();
        same('Local', $w2->store->read('config')['owner_name'], "$why: not applied");
        check((bool) array_filter($log, fn ($l) => str_contains($l, 'no valid')), "$why: logged");
    }
});

test('bundle schema: the chain settings and the trusted fields are checked', function (): void {
    $w = new SW(SW::P1);
    $w->answerStates();
    $b = bundleFrom($w, 41);
    $b['config']['trust'] = ['mode' => 'all_at_once', 'interval' => 45, 'window' => 36];
    $b['contacts'] = ['contacts' => [['id' => 'c_1', 'name' => 'Kim', 'trusted' => true, 'trusted_order' => 1], ['id' => 'c_2', 'name' => 'Lee', 'trusted' => false, 'trusted_order' => null]]];
    $w->net->routes['p1.example.org']['bundle'] = fn () => $b;
    $w->sync->run();
    same(['mode' => 'all_at_once', 'interval' => 45, 'window' => 36], $w->store->read('config')['trust'], 'good settings kept');
    same(true, $w->store->read('contacts')['contacts'][0]['trusted'], 'trusted flag kept');

    foreach ([
        'trust not an object' => fn (array $x): array => ['config' => ['trust' => 'yes'] + $x['config']] + $x,
        'unknown mode' => fn (array $x): array => ['config' => ['trust' => ['mode' => 'x', 'interval' => 'auto', 'window' => 24]] + $x['config']] + $x,
        'interval too small' => fn (array $x): array => ['config' => ['trust' => ['mode' => 'sequential', 'interval' => 5, 'window' => 24]] + $x['config']] + $x,
        'window too long' => fn (array $x): array => ['config' => ['trust' => ['mode' => 'sequential', 'interval' => 'auto', 'window' => 500]] + $x['config']] + $x,
        'trusted not a flag' => fn (array $x): array => ['contacts' => ['contacts' => [['id' => 'c_1', 'name' => 'Kim', 'trusted' => 'yes']]]] + $x,
        'order not a number' => fn (array $x): array => ['contacts' => ['contacts' => [['id' => 'c_1', 'name' => 'Kim', 'trusted' => true, 'trusted_order' => 'first']]]] + $x,
    ] as $why => $mutate) {
        $w2 = new SW(SW::P1);
        $w2->answerStates();
        $bad = $mutate(bundleFrom($w2, 42));
        $w2->net->routes['p1.example.org']['bundle'] = fn () => $bad;
        $log = $w2->sync->run();
        same('Local', $w2->store->read('config')['owner_name'], "$why: not applied");
        check((bool) array_filter($log, fn ($l) => str_contains($l, 'no valid')), "$why: logged");
    }
});

function sharesDoc(SW $w): array
{
    $pk = $w->store->read('node')['share_pk'];
    $box = \DPS\ShareBox::sealForTests($pk, $w->id, str_repeat("\x05", 33));
    return ['version' => 4, 'k' => 1, 'n' => 1, 'cluster_version' => 2, 'boxes' => [$w->id => $box]];
}

test('a bundle with shares keeps them and leaves no release key', function (): void {
    $w = new SW(SW::P1);
    $w->answerStates();
    $w->store->write('release', ['rk' => 'secret']);
    $b = bundleFrom($w, 31);
    $b['vault']['shares'] = sharesDoc($w);
    $w->net->routes['p1.example.org']['bundle'] = fn () => $b;
    $w->sync->run();
    $v = $w->store->read('vault');
    same([$w->id], array_keys($v['shares']['boxes']), 'boxes kept');
    same(1, $v['shares']['k'], 'k kept');
    check(!file_exists($w->store->path('release')), 'no release.json');
    check((new \DPS\Vault($w->store))->myShare($w->id) !== null, 'share opens');
});

test('invalid shares refuse the whole bundle', function (): void {
    $w = new SW(SW::P1);
    $w->answerStates();
    $b = bundleFrom($w, 32);
    $b['vault']['shares'] = ['k' => 5, 'n' => 1, 'cluster_version' => 1, 'boxes' => []];
    $w->net->routes['p1.example.org']['bundle'] = fn () => $b;
    $log = $w->sync->run();
    same('Local', $w->store->read('config')['owner_name'], 'not applied');
    check((bool) array_filter($log, fn ($l) => str_contains($l, 'no valid')), 'logged');
});

test('Sync::bundle is for the primary, and unchanged on the same version', function (): void {
    $w = new SW(SW::P1);
    try {
        Sync::bundle($w->store, 0);
        check(false, 'a secondary must refuse');
    } catch (\LogicException) {
        check(true, 'refused');
    }
    $p = new SW(null);
    $c = Cluster::read($p->store);
    $c['primary'] = $p->id;
    Cluster::save($p->store, $c);
    $p->store->write('contacts', ['contacts' => []]);
    $b = Sync::bundle($p->store, 0);
    check(isset($b['version']) && is_int($b['version']) && $b['version'] > 0, 'version');
    same(['version', 'config', 'contacts', 'vault', 'cluster'], array_keys($b), 'keys');
    same(['unchanged' => true], Sync::bundle($p->store, $b['version']), 'unchanged');
    $p->store->write('contacts', ['contacts' => [['id' => 'c_9']]]);
    check(isset(Sync::bundle($p->store, $b['version'])['version']), 'changed after edit');
});

finish();
