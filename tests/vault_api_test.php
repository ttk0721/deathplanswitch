<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Store, Vault, VaultApi};
require __DIR__ . '/lib.php';

function b(int $n): string { return rtrim(strtr(base64_encode(str_repeat('a', $n)), '+/', '-_'), '='); }
function sealed(): array { return ['iv' => b(12), 'ct' => b(20)]; }
function ownerDoc(): array { return ['salt' => b(16), 'iterations' => 1000, 'wrapped_rk' => sealed(), 'check' => sealed()]; }
function api(): VaultApi {
    $dir = sys_get_temp_dir() . '/dps-vapi-' . bin2hex(random_bytes(5)); mkdir($dir, 0700);
    return new VaultApi(new Vault(new Store($dir)));
}
function call(VaultApi $api, string $method, string $a, array $o = []): array {
    return $api->handle($o + ['method' => $method, 'a' => $a, 'query' => [], 'body' => '', 'owner' => false, 'csrf' => false, 'released' => false]);
}
const OWN = ['owner' => true, 'csrf' => true];

test('status is public', function (): void {
    $r = call(api(), 'GET', 'status');
    same(200, $r['status'], 'status');
    same(false, $r['json']['initialised'], 'initialised');
    same(false, $r['json']['released'], 'released');
});
test('owner routes need the owner and the token', function (): void {
    $api = api();
    same(401, call($api, 'GET', 'owner')['status'], 'not owner');
    same(200, call($api, 'GET', 'owner', ['owner' => true])['status'], 'owner reads');
    $body = json_encode(['owner' => ownerDoc(), 'rk' => 'abc']);
    same(401, call($api, 'POST', 'init', ['body' => $body])['status'], 'init anonymous');
    same(403, call($api, 'POST', 'init', ['body' => $body, 'owner' => true])['status'], 'init no csrf');
    same(200, call($api, 'POST', 'init', ['body' => $body] + OWN)['status'], 'init');
    same(true, call($api, 'GET', 'status')['json']['initialised'], 'now initialised');
    foreach (['recipient', 'recipient_delete', 'package', 'package_delete'] as $a) {
        same(403, call($api, 'POST', $a, ['owner' => true, 'body' => '{}'])['status'], "$a no csrf");
        same(401, call($api, 'POST', $a, ['csrf' => true, 'body' => '{}'])['status'], "$a anonymous");
    }
    same(403, call($api, 'PUT', 'chunk', ['owner' => true])['status'], 'chunk put no csrf');
});
test('public, release and chunk wait for the release', function (): void {
    $api = api();
    $api->handle(['method' => 'PUT', 'a' => 'chunk', 'query' => ['file' => 'f_0123456789abcdef', 'n' => '0'], 'body' => 'abc'] + OWN);
    call($api, 'POST', 'init', ['body' => json_encode(['owner' => ownerDoc(), 'rk' => 'abc'])] + OWN);
    $q = ['query' => ['file' => 'f_0123456789abcdef', 'n' => '0']];
    foreach (['public', 'release', 'chunk'] as $a) {
        $r = call($api, 'GET', $a, $q);
        same(403, $r['status'], "$a before");
        same(['error' => 'not released'], $r['json'], "$a before body");
        same(200, call($api, 'GET', $a, $q + ['released' => true])['status'], "$a after");
    }
    same(['rk' => 'abc'], call($api, 'GET', 'release', ['released' => true])['json'], 'rk');
    $f = call($api, 'GET', 'chunk', $q + ['released' => true]);
    same('abc', file_get_contents($f['file']), 'chunk file');
});
test('bad input is 400, big chunk 413, unknown route 404', function (): void {
    $api = api();
    same(400, call($api, 'POST', 'init', ['body' => 'not json'] + OWN)['status'], 'bad json');
    same(400, call($api, 'POST', 'init', ['body' => '5'] + OWN)['status'], 'not an object');
    same(400, call($api, 'POST', 'recipient', ['body' => '{"id":"bad"}'] + OWN)['status'], 'bad recipient');
    $q = ['file' => 'f_0123456789abcdef', 'n' => '0'];
    same(413, call($api, 'PUT', 'chunk', ['query' => $q, 'body' => str_repeat('x', 4194369)] + OWN)['status'], 'big');
    same(400, call($api, 'PUT', 'chunk', ['query' => ['file' => 'f_0123456789abcdef', 'n' => '-1'], 'body' => 'x'] + OWN)['status'], 'negative n');
    same(400, call($api, 'PUT', 'chunk', ['query' => ['file' => '../x', 'n' => '0'], 'body' => 'x'] + OWN)['status'], 'bad file');
    same(404, call($api, 'GET', 'nope', OWN)['status'], 'unknown route');
    same(404, call($api, 'POST', 'status', OWN)['status'], 'wrong method');
    same(404, call($api, 'GET', 'chunk', ['owner' => true, 'query' => $q])['status'], 'missing chunk');
});
test('owner may read chunks before release', function (): void {
    $api = api();
    $q = ['file' => 'f_0123456789abcdef', 'n' => '0'];
    call($api, 'PUT', 'chunk', ['query' => $q, 'body' => 'abc'] + OWN);
    $r = call($api, 'GET', 'chunk', ['owner' => true, 'query' => $q]);
    same(200, $r['status'], 'status');
    same('abc', file_get_contents($r['file']), 'content');
});
test('authorisation matrix: every route, caller and release state', function (): void {
    // route => [method, who, needs csrf]
    $routes = [
        'status' => ['GET', 'any', false], 'owner' => ['GET', 'owner', false],
        'init' => ['POST', 'owner', true], 'recipient' => ['POST', 'owner', true],
        'recipient_delete' => ['POST', 'owner', true], 'package' => ['POST', 'owner', true],
        'package_delete' => ['POST', 'owner', true], 'chunk' => ['PUT', 'owner', true],
        'chunk_get' => ['GET', 'owner_or_released', false], 'public' => ['GET', 'released', false],
        'release' => ['GET', 'released', false],
    ];
    $callers = [
        'anonymous' => ['owner' => false, 'csrf' => false], 'anonymous with token' => ['owner' => false, 'csrf' => true],
        'owner no token' => ['owner' => true, 'csrf' => false], 'owner with token' => ['owner' => true, 'csrf' => true],
    ];
    $q = ['file' => 'f_0123456789abcdef', 'n' => '0'];
    foreach ($routes as $name => [$method, $who, $csrf]) {
        foreach ([false, true] as $released) {
            foreach ($callers as $label => $c) {
                $a = $name === 'chunk_get' ? 'chunk' : $name;
                $r = call(api(), $method, $a, ['query' => $q, 'body' => '{}', 'released' => $released] + $c);
                $denied = in_array($r['status'], [401, 403], true);
                $allowed = match ($who) {
                    'any' => true,
                    'owner' => $c['owner'] && (!$csrf || $c['csrf']),
                    'released' => $released,
                    'owner_or_released' => $released || $c['owner'],
                };
                $cell = "$name / $label / " . ($released ? 'released' : 'sealed');
                same(!$allowed, $denied, $cell);
                if (!$allowed && $who === 'owner') {
                    same($c['owner'] ? 403 : 401, $r['status'], "$cell status");
                }
                if (!$allowed && $who !== 'owner') {
                    same(['error' => 'not released'], $r['json'], "$cell body");
                }
            }
        }
    }
});
test('the release key never leaks into other answers before release', function (): void {
    $api = api();
    call($api, 'POST', 'init', ['body' => json_encode(['owner' => ownerDoc(), 'rk' => 'SECRETRK'])] + OWN);
    foreach (['status', 'owner'] as $a) {
        $r = call($api, 'GET', $a, ['owner' => true]);
        check(!str_contains(json_encode($r), 'SECRETRK'), "$a leaks the release key");
    }
});
test('chunk arguments cannot escape the blob folder', function (): void {
    $api = api();
    foreach (['../f_0123456789abcdef', 'f_0123456789abcdef/../x', "f_0123456789abcdef\0", 'f_0123456789abcdef.', 'F_0123456789ABCDEF'] as $file) {
        foreach (['PUT', 'GET'] as $m) {
            $r = call($api, $m, 'chunk', ['query' => ['file' => $file, 'n' => '0'], 'body' => 'x'] + OWN);
            same(400, $r['status'], "$m " . json_encode($file));
        }
    }
    foreach (['01', '1.0', '1e3', ' 1', '-0', '100000', '123456', ''] as $n) {
        same(400, call($api, 'GET', 'chunk', ['query' => ['file' => 'f_0123456789abcdef', 'n' => $n], 'owner' => true])['status'], "n=$n");
    }
    same(400, call($api, 'GET', 'chunk', ['query' => ['file' => ['x'], 'n' => '0'], 'owner' => true])['status'], 'array file');
});
test('deep or huge JSON is refused', function (): void {
    $api = api();
    same(400, call($api, 'POST', 'recipient', ['body' => str_repeat('[', 40) . str_repeat(']', 40)] + OWN)['status'], 'too deep');
    same(413, call($api, 'POST', 'package', ['body' => str_repeat(' ', 4194369)] + OWN)['status'], 'too big');
});
finish();
