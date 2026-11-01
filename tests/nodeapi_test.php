<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Cluster, Node, NodeApi, Store};
require __DIR__ . '/lib.php';

const NOW = 1_800_000_000;

function mkStore(): Store
{
    $dir = sys_get_temp_dir() . '/dps-napi-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700, true);
    return new Store($dir);
}

/** Two nodes (client "a", server "b"), one shared cluster; b is the primary unless told otherwise. */
function pair(string $primary = 'b'): array
{
    $a = mkStore();
    $b = mkStore();
    $ia = Node::init($a, 'a');
    $ib = Node::init($b, 'b');
    $entry = static fn (array $i, int $rank): array => $i + ['url' => 'http://192.168.1.' . ($rank + 2), 'rank' => $rank];
    $cluster = ['k' => 1, 'primary' => ($primary === 'b' ? $ib : $ia)['id'], 'nodes' => [$entry($ia, 0), $entry($ib, 1)]];
    Cluster::save($a, $cluster);
    Cluster::save($b, $cluster);
    return [$a, $b, $ia, $ib];
}

$calls = [];
function api(Store $b, array &$calls): NodeApi
{
    return new NodeApi($b, [
        'state' => fn (): array => ['node' => 'b', 'armed' => true],
        'checkin' => function (int $at, string $via) use (&$calls): void { $calls[] = ['checkin', $at, $via]; },
        'nok' => function (string $answer, int $at) use (&$calls): void { $calls[] = ['nok', $answer, $at]; },
        'bundle' => function (int $since): array {
            if ($since === 99) { throw new \LogicException('not primary'); }
            return ['unchanged' => true];
        },
        'blob' => fn (string $f, int $n): ?string => $f === 'f1' ? __FILE__ : null,
    ]);
}

/** A signed request from node a. */
function signed(Store $a, array $ia, string $method, string $a_, array $query = [], string $body = '', ?int $time = null, ?string $nonce = null, int $now = NOW): array
{
    $pathQuery = '/node-api.php?' . http_build_query(['a' => $a_] + $query);
    $time ??= $now;
    $nonce ??= bin2hex(random_bytes(8));
    return [
        'method' => $method, 'a' => $a_, 'pathQuery' => $pathQuery, 'query' => ['a' => $a_] + $query, 'body' => $body, 'now' => $now,
        'headers' => ['node' => $ia['id'], 'time' => (string)$time, 'nonce' => $nonce,
            'signature' => Node::sign($a, Node::canonical($method, $pathQuery, $body, $time, $nonce))],
    ];
}

test('a signed state call gives 200 and the handler state', function () use (&$calls): void {
    [$a, $b, $ia] = pair();
    $r = api($b, $calls)->handle(signed($a, $ia, 'GET', 'state'));
    same(200, $r['status'], 'status');
    same(['node' => 'b', 'armed' => true], $r['json'], 'json');
});
test('unsigned, unknown node, non-member key and tampered body give 401', function () use (&$calls): void {
    [$a, $b, $ia] = pair();
    $api = api($b, $calls);
    $unsigned = ['method' => 'GET', 'a' => 'state', 'pathQuery' => '/node-api.php?a=state', 'query' => ['a' => 'state'], 'body' => '', 'headers' => [], 'now' => NOW];
    same([401, ['error' => 'not signed']], [$api->handle($unsigned)['status'], $api->handle($unsigned)['json']], 'unsigned');
    // A valid signature from a key that is not in the cluster.
    $c = mkStore();
    $ic = Node::init($c, 'c');
    $r = $api->handle(signed($c, $ic, 'GET', 'state'));
    same(401, $r['status'], 'non-member');
    $req = signed($a, $ia, 'POST', 'checkin', [], json_encode(['at' => NOW, 'via' => 'web']));
    $req['body'] = json_encode(['at' => NOW, 'via' => 'evil']);
    same(401, $api->handle($req)['status'], 'tampered body');
    $req = signed($a, $ia, 'GET', 'state');
    $req['pathQuery'] = '/node-api.php?a=bundle';
    same(401, $api->handle($req)['status'], 'tampered path');
});
test('unknown route is 404 before the signature is looked at', function () use (&$calls): void {
    [$a, $b, $ia] = pair();
    same(404, api($b, $calls)->handle(signed($a, $ia, 'GET', 'nope'))['status'], 'route');
    same(404, api($b, $calls)->handle(signed($a, $ia, 'POST', 'state'))['status'], 'method');
});
test('time window is 300 s; a replayed nonce is 409; a bad signature does not burn the nonce', function () use (&$calls): void {
    [$a, $b, $ia] = pair();
    $api = api($b, $calls);
    same(408, $api->handle(signed($a, $ia, 'GET', 'state', [], '', NOW - 301))['status'], 'old');
    same(408, $api->handle(signed($a, $ia, 'GET', 'state', [], '', NOW + 301))['status'], 'future');
    same(200, $api->handle(signed($a, $ia, 'GET', 'state', [], '', NOW - 300))['status'], 'edge');
    $req = signed($a, $ia, 'GET', 'state');
    same(200, $api->handle($req)['status'], 'first');
    same(409, $api->handle($req)['status'], 'replay');
    $bad = signed($a, $ia, 'GET', 'state', [], '', null, 'abcdef0123456789');
    $good = $bad;
    $bad['headers']['signature'] = 'AAAA';
    same(401, $api->handle($bad)['status'], 'bad sig');
    same(200, $api->handle($good)['status'], 'nonce not burned by a forged request');
});
test('checkin passes at and via; a future at or bad input is 400', function () use (&$calls): void {
    [$a, $b, $ia] = pair();
    $calls = [];
    $api = api($b, $calls);
    $body = json_encode(['at' => NOW - 5, 'via' => 'link']);
    same(200, $api->handle(signed($a, $ia, 'POST', 'checkin', [], $body))['status'], 'ok');
    same([['checkin', NOW - 5, 'link']], $calls, 'handler called');
    same(400, $api->handle(signed($a, $ia, 'POST', 'checkin', [], json_encode(['at' => NOW + 3600, 'via' => 'link'])))['status'], 'future');
    same(400, $api->handle(signed($a, $ia, 'POST', 'checkin', [], 'not json'))['status'], 'not json');
    same(400, $api->handle(signed($a, $ia, 'POST', 'checkin', [], json_encode(['at' => 'x', 'via' => 'l'])))['status'], 'bad at');
    same(1, count($calls), 'no further calls');
});
test('nok passes the answer; anything but fine or wrong is 400', function () use (&$calls): void {
    [$a, $b, $ia] = pair();
    $calls = [];
    $api = api($b, $calls);
    same(200, $api->handle(signed($a, $ia, 'POST', 'nok', [], json_encode(['answer' => 'wrong', 'at' => NOW - 1])))['status'], 'ok');
    same([['nok', 'wrong', NOW - 1]], $calls, 'called');
    same(400, $api->handle(signed($a, $ia, 'POST', 'nok', [], json_encode(['answer' => 'maybe', 'at' => NOW - 1])))['status'], 'bad answer');
});
test('bundle: json from the handler, 403 when it throws LogicException, 400 on a bad since', function () use (&$calls): void {
    [$a, $b, $ia] = pair();
    $api = api($b, $calls);
    $r = $api->handle(signed($a, $ia, 'GET', 'bundle', ['since' => '5']));
    same([200, ['unchanged' => true]], [$r['status'], $r['json']], 'ok');
    same(403, $api->handle(signed($a, $ia, 'GET', 'bundle', ['since' => '99']))['status'], 'not primary');
    same(400, $api->handle(signed($a, $ia, 'GET', 'bundle', ['since' => 'x']))['status'], 'bad since');
});
test('blob: file result, 404 when missing, 400 on traversal', function () use (&$calls): void {
    [$a, $b, $ia] = pair();
    $api = api($b, $calls);
    $r = $api->handle(signed($a, $ia, 'GET', 'blob', ['file' => 'f1', 'n' => '0']));
    same([200, __FILE__], [$r['status'], $r['file'] ?? null], 'file');
    same(404, $api->handle(signed($a, $ia, 'GET', 'blob', ['file' => 'f2', 'n' => '0']))['status'], 'missing');
    same(400, $api->handle(signed($a, $ia, 'GET', 'blob', ['file' => '../config', 'n' => '0']))['status'], 'traversal');
    same(400, $api->handle(signed($a, $ia, 'GET', 'blob', ['file' => 'f1', 'n' => '-1']))['status'], 'negative n');
});
finish();
