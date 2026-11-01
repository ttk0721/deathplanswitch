<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Cluster, Node, NodeApi, Store};
require __DIR__ . '/lib.php';

const NOW = 1_800_000_000;

function mk(): Store
{
    $dir = sys_get_temp_dir() . '/dps-sec-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700, true);
    return new Store($dir);
}

function setup(string $primary = 'b'): array
{
    $a = mk();
    $b = mk();
    $c = mk();
    $ia = Node::init($a, 'a');
    $ib = Node::init($b, 'b');
    $ic = Node::init($c, 'outsider');
    $e = static fn (array $i, int $r): array => $i + ['url' => 'http://192.168.1.' . ($r + 2), 'rank' => $r];
    Cluster::save($b, ['k' => 1, 'primary' => ($primary === 'b' ? $ib : $ia)['id'], 'nodes' => [$e($ia, 0), $e($ib, 1)]]);
    return [$a, $b, $c, $ia, $ib, $ic];
}

$log = [];
function api(Store $b, array &$log): NodeApi
{
    return new NodeApi($b, [
        'state' => fn (): array => ['ok' => 1],
        'checkin' => function (int $at, string $via) use (&$log): void { $log[] = ['checkin', $at, $via]; },
        'nok' => function (string $x, int $at) use (&$log): void { $log[] = ['nok', $x, $at]; },
        'bundle' => function (int $s) use (&$log): array { $log[] = ['bundle']; return ['secret' => 1]; },
        'blob' => function (string $f, int $n) use (&$log): ?string { $log[] = ['blob', $f]; return null; },
    ]);
}

function req(Store $s, array $id, string $method, string $pathQuery, array $query, string $body = '', ?int $time = null, ?string $nonce = null, ?string $canonicalQuery = null): array
{
    $time ??= NOW;
    $nonce ??= bin2hex(random_bytes(8));
    return [
        'method' => $method, 'a' => (string)($query['a'] ?? ''), 'pathQuery' => $pathQuery, 'query' => $query, 'body' => $body, 'now' => NOW,
        'headers' => ['node' => $id['id'], 'time' => (string)$time, 'nonce' => $nonce,
            'signature' => Node::sign($s, Node::canonical($method, $canonicalQuery ?? $pathQuery, $body, $time, $nonce))],
    ];
}

test('a non-member cannot check in, even with a valid signature of its own key', function () use (&$log): void {
    [$a, $b, $c, $ia, $ib, $ic] = setup();
    $body = json_encode(['at' => NOW, 'via' => 'x']);
    $r = api($b, $log)->handle(req($c, $ic, 'POST', '/node-api.php?a=checkin', ['a' => 'checkin'], $body));
    same(401, $r['status'], 'unknown node');
    $forged = req($c, $ic, 'POST', '/node-api.php?a=checkin', ['a' => 'checkin'], $body);
    $forged['headers']['node'] = $ia['id'];
    same(401, api($b, $log)->handle($forged)['status'], 'claims to be a');
    same([], $log, 'handler untouched');
});

test('duplicate a parameters: dispatch must match the signed query', function () use (&$log): void {
    [$a, $b, $c, $ia] = setup();
    $r = req($a, $ia, 'GET', '/node-api.php?a=state&a=bundle', ['a' => 'bundle']);
    $r['query'] = ['a' => 'state'];
    $r['a'] = 'state';
    same(400, api($b, $log)->handle($r)['status'], 'query differs from the signed one');
    same([], $log, 'no handler ran');
});

test('signature over another method, body or path is refused', function () use (&$log): void {
    [$a, $b, $c, $ia] = setup();
    $body = json_encode(['at' => NOW, 'via' => 'x']);
    $r = req($a, $ia, 'GET', '/node-api.php?a=checkin', ['a' => 'checkin'], $body);
    $r['method'] = 'POST';
    same(401, api($b, $log)->handle($r)['status'], 'method swapped');
    $r = req($a, $ia, 'POST', '/node-api.php?a=checkin', ['a' => 'checkin'], $body);
    $r['body'] = json_encode(['at' => NOW + 30, 'via' => 'x']);
    same(401, api($b, $log)->handle($r)['status'], 'body swapped');
    $r = req($a, $ia, 'POST', '/node-api.php?a=nok', ['a' => 'nok'], $body, null, null, '/node-api.php?a=checkin');
    same(401, api($b, $log)->handle($r)['status'], 'path swapped');
    same([], $log, 'no handler ran');
});

test('a replayed check-in is refused; nonces of the wrong shape are refused', function () use (&$log): void {
    [$a, $b, $c, $ia] = setup();
    $body = json_encode(['at' => NOW, 'via' => 'x']);
    $r = req($a, $ia, 'POST', '/node-api.php?a=checkin', ['a' => 'checkin'], $body);
    same(200, api($b, $log)->handle($r)['status'], 'first');
    same(409, api($b, $log)->handle($r)['status'], 'replay');
    foreach (['', 'zz', 'abc', str_repeat('a', 65), "aabbccdd11223344\n", 'AABBCCDD11223344'] as $n) {
        $r = req($a, $ia, 'POST', '/node-api.php?a=checkin', ['a' => 'checkin'], $body, null, $n);
        $s = api($b, $log)->handle($r)['status'];
        check($s === 401, 'nonce ' . json_encode($n) . " gave $s");
    }
    same(1, count($log), 'one handler call');
});

test('time window: far future and far past are refused, a trailing newline too', function () use (&$log): void {
    [$a, $b, $c, $ia] = setup();
    foreach ([NOW + 100000, NOW - 301, NOW + 301, 0] as $t) {
        $r = req($a, $ia, 'GET', '/node-api.php?a=state', ['a' => 'state'], '', $t);
        $s = api($b, $log)->handle($r)['status'];
        check($s === 408 || $s === 401, "time $t gave $s");
    }
    $r = req($a, $ia, 'GET', '/node-api.php?a=state', ['a' => 'state']);
    $r['headers']['time'] = NOW . "\n";
    same(401, api($b, $log)->handle($r)['status'], 'newline in time');
});

test('check-in at and via limits; via cannot carry a newline', function () use (&$log): void {
    $log = [];
    [$a, $b, $c, $ia] = setup();
    foreach ([['at' => NOW + 61, 'via' => 'x'], ['at' => -1, 'via' => 'x'], ['at' => NOW, 'via' => "x\nFAKE LOG LINE"],
        ['at' => NOW, 'via' => "x\n"], ['at' => NOW, 'via' => str_repeat('a', 41)], ['at' => (string)NOW, 'via' => 'x'], ['at' => true, 'via' => 'x']] as $b1) {
        $r = req($a, $ia, 'POST', '/node-api.php?a=checkin', ['a' => 'checkin'], json_encode($b1));
        same(400, api($b, $log)->handle($r)['status'], 'bad ' . json_encode($b1));
    }
    same([], $log, 'no handler ran');
});

test('blob: traversal and newline in file name are refused', function () use (&$log): void {
    $log = [];
    [$a, $b, $c, $ia] = setup();
    foreach (['../node', '..', 'a/b', "f1\n", '', str_repeat('a', 65)] as $f) {
        $q = ['a' => 'blob', 'file' => $f, 'n' => '1'];
        $r = req($a, $ia, 'GET', '/node-api.php?' . http_build_query($q), $q);
        same(400, api($b, $log)->handle($r)['status'], 'file ' . json_encode($f));
    }
    $q = ['a' => 'blob', 'file' => 'f1', 'n' => "1\n"];
    same(400, api($b, $log)->handle(req($a, $ia, 'GET', '/node-api.php?' . http_build_query($q), $q))['status'], 'n newline');
    same([], $log, 'no handler ran');
});

test('the bundle is served to members only, and only by the primary', function () use (&$log): void {
    [$a, $b, $c, $ia, $ib, $ic] = setup('b');
    $q = ['a' => 'bundle', 'since' => '0'];
    $pq = '/node-api.php?' . http_build_query($q);
    same(200, api($b, $log)->handle(req($a, $ia, 'GET', $pq, $q))['status'], 'member asks the primary');
    same(401, api($b, $log)->handle(req($c, $ic, 'GET', $pq, $q))['status'], 'outsider');
    $log = [];
    [$a2, $b2, , $ia2] = setup('a');
    same(403, api($b2, $log)->handle(req($a2, $ia2, 'GET', $pq, $q))['status'], 'a non-primary does not serve it');
    same([], $log, 'bundle handler not called');
});

test('the nonce store drops old entries and a bad signature stores no nonce', function () use (&$log): void {
    [$a, $b, $c, $ia] = setup();
    for ($i = 0; $i < 20; $i++) {
        Node::seenNonce($b, $ia['id'], sprintf('%016x', $i), NOW - 1000);
    }
    Node::seenNonce($b, $ia['id'], 'ffffffffffffffff', NOW);
    same(1, count($b->read('nonces')), 'old entries pruned');
    $before = $b->read('nonces');
    $bad = ['method' => 'GET', 'a' => 'state', 'pathQuery' => '/node-api.php?a=state', 'query' => ['a' => 'state'], 'body' => '', 'now' => NOW,
        'headers' => ['node' => $ia['id'], 'time' => (string)NOW, 'nonce' => 'aaaaaaaaaaaaaaaa', 'signature' => 'AAAA']];
    api($b, $log)->handle($bad);
    same($before, $b->read('nonces'), 'nothing stored');
});

test('cluster URLs: userinfo, query, fragment, public http, ipv6 and look-alikes are refused', function (): void {
    [$a, $b, , $ia, $ib] = setup();
    $try = function (string $url) use ($b, $ia, $ib): bool {
        try {
            Cluster::save($b, ['k' => 1, 'primary' => $ib['id'], 'nodes' => [$ia + ['url' => $url, 'rank' => 0], $ib + ['url' => 'https://b.example.org', 'rank' => 1]]]);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    };
    foreach (['http://8.8.8.8', 'http://[::1]', 'http://10.0.0.1.evil.com', 'http://evil.com@10.0.0.1', 'https://user:pw@x.example.org',
        'https://x.example.org/?a=1', 'https://x.example.org/#f', 'http://010.0.0.1', 'ftp://10.0.0.1', 'http://x.local.evil.com', 'file:///etc/passwd'] as $bad) {
        check(!$try($bad), "refused $bad");
    }
    foreach (['https://x.example.org/dps', 'http://192.168.1.5:8080', 'http://box.ts.net'] as $ok) {
        check($try($ok), "accepted $ok");
    }
});

test('cluster ids cannot carry a trailing newline', function (): void {
    [$a, $b, , $ia] = setup();
    $bad = $ia;
    $bad['id'] = $ia['id'] . "\n";
    $threw = false;
    try {
        Cluster::save($b, ['k' => 1, 'primary' => null, 'nodes' => [$bad + ['url' => 'https://x.example.org', 'rank' => 0]]]);
    } catch (\InvalidArgumentException) {
        $threw = true;
    }
    check($threw, 'id with newline refused');
});

test('node.json is private and the public identity has no secrets', function (): void {
    [$a, , , $ia] = setup();
    same('0600', substr(sprintf('%o', fileperms($a->path('node'))), -4), 'mode');
    $k = array_keys($ia);
    sort($k);
    same(['id', 'name', 'share_pk', 'sign_pk'], $k, 'public keys only');
});

test('node-api.php sends the lock-down headers and starts no session', function (): void {
    $src = (string)file_get_contents(dirname(__DIR__) . '/node-api.php');
    foreach (['Cache-Control: no-store', 'nosniff', "default-src 'none'", 'no-referrer'] as $h) {
        check(str_contains($src, $h), "header $h");
    }
    check(!str_contains($src, 'session_start') && !str_contains((string)file_get_contents(dirname(__DIR__) . '/app/bootstrap.php'), 'session_start'), 'no session');
});

finish();
