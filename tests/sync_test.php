<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Clock, Cluster, Coordinator, Engine, Node, Outbox, PeerClient, Store, Sync, Transport, Email};
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
    public function __invoke(string $method, string $url, array $headers, string $body, int $timeout = 20): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $a = (string) ($q['a'] ?? '');
        unset($q['a']);
        $this->calls[] = ['host' => $host, 'method' => $method, 'a' => $a, 'query' => $q, 'body' => $body];
        $fn = $this->routes[$host][$a] ?? null;
        if ($fn === null) {
            return [404, '{"error":"not found"}'];
        }
        $out = $fn($method, $q, $body);
        return [200, is_array($out) ? json_encode($out) : $out];
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
        $nodes = [nd($this->id, 2, 'https://self.example.org', $self['sign_pk']), nd(self::P1, 0, 'https://p1.example.org')];
        if ($two) {
            $nodes[] = nd(self::P2, 1, 'https://p2.example.org');
        }
        Cluster::save($this->store, ['k' => 1, 'primary' => $primary, 'isolation_hours' => 48, 'step_delay_min' => 30, 'nodes' => $nodes]);
        $this->store->write('config', ['owner_name' => 'Local', 'base_url' => 'https://local.example.org', 'tick_key' => 'ltk',
            'health_key' => 'lhk', 'installed_at' => 111]);
        $this->net = new Net();
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
    $b['vault'] = ['recipients' => [], 'packages' => [['id' => 'p_0123456789abcdef', 'files' => [['id' => $fid, 'chunks' => 2, 'size' => 10]]]]];
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
    $b['vault'] = ['recipients' => [], 'packages' => [['id' => 'p_0123456789abcdef', 'files' => [['id' => $fid, 'chunks' => 3, 'size' => 10]]]]];
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
