<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Node, ShareBox, Store, Vault, VaultApi};
require __DIR__ . '/lib.php';

const OWN = ['owner' => true, 'csrf' => true];

function b(int $n): string { return rtrim(strtr(base64_encode(str_repeat('a', $n)), '+/', '-_'), '='); }
function sealed(): array { return ['iv' => b(12), 'ct' => b(20)]; }
function ownerDoc(): array { return ['salt' => b(16), 'iterations' => 1000, 'wrapped_rk' => sealed(), 'check' => sealed()]; }
function tmpStore(): Store {
    $dir = sys_get_temp_dir() . '/dps-vsh-' . bin2hex(random_bytes(5)); mkdir($dir, 0700);
    return new Store($dir);
}
function call(VaultApi $api, string $method, string $a, array $o = []): array {
    return $api->handle($o + ['method' => $method, 'a' => $a, 'query' => [], 'body' => '', 'owner' => false, 'csrf' => false, 'released' => false]);
}
function fakeBox(): array { return ['epk' => b(91), 'iv' => b(12), 'ct' => b(49)]; }
/** A vault on a node with an identity, and shares sealed to it and to a second id. */
function setup(bool $withShares = true, bool $ownBox = true): array {
    $store = tmpStore();
    $me = Node::init($store, 'me')['id'];
    $pk = $store->read('node')['share_pk'];
    $vault = new Vault($store);
    $vault->initialise(ownerDoc(), 'cmtleQ');
    $share = chr(2) . str_repeat("\x07", 32);
    $other = 'n_' . bin2hex(random_bytes(4));
    if ($withShares) {
        $boxes = [$other => fakeBox()];
        $boxes[$ownBox ? $me : 'n_' . bin2hex(random_bytes(4))] = ShareBox::sealForTests($pk, $me, $share);
        $vault->saveShares(['k' => 2, 'n' => 2, 'cluster_version' => 3, 'boxes' => $boxes]);
    }
    return [$store, $vault, $me, $other, $share];
}
function throws(callable $f, string $what): void {
    try { $f(); } catch (\InvalidArgumentException) { check(true, $what); return; }
    check(false, $what . ' (no exception)');
}

test('saveShares validates', function (): void {
    [, $vault, $me] = setup(false);
    $o = 'n_' . bin2hex(random_bytes(4));
    $ok = ['k' => 2, 'n' => 2, 'cluster_version' => 1, 'boxes' => [$me => fakeBox(), $o => fakeBox()]];
    throws(fn() => $vault->saveShares(['k' => 3] + $ok), 'k > n');
    throws(fn() => $vault->saveShares(['k' => 0] + $ok), 'k 0');
    throws(fn() => $vault->saveShares(['n' => 10, 'k' => 2] + $ok), 'n 10');
    throws(fn() => $vault->saveShares(['boxes' => [$me => fakeBox()]] + $ok), 'missing box');
    throws(fn() => $vault->saveShares(['boxes' => [$me => fakeBox(), 'bad' => fakeBox()]] + $ok), 'bad box key');
    throws(fn() => $vault->saveShares(['boxes' => [$me => ['epk' => '!!', 'iv' => b(12), 'ct' => b(49)], $o => fakeBox()]] + $ok), 'bad epk');
    throws(fn() => $vault->saveShares(['boxes' => [$me => ['iv' => b(12), 'ct' => b(49)], $o => fakeBox()]] + $ok), 'no epk');
    throws(fn() => $vault->saveShares(['boxes' => [$me => ['epk' => b(91), 'iv' => b(8), 'ct' => b(49)], $o => fakeBox()]] + $ok), 'short iv');
    same(null, $vault->shares(), 'nothing stored');
    $vault->saveShares($ok);
    same(2, $vault->shares()['k'], 'stored k');
    same(1, $vault->shares()['version'], 'first version');
    $vault->saveShares($ok);
    same(2, $vault->shares()['version'], 'version bumps');
});

test('saving shares deletes the plain release key', function (): void {
    [$store, $vault] = setup(false);
    same('cmtleQ', $vault->releaseKey(), 'key before');
    [$store, $vault] = setup(true);
    same(null, $vault->releaseKey(), 'key gone');
    check(!is_file($store->path('release')), 'release.json removed');
    check(!is_file($store->path('release') . '.bak'), 'backup removed');
    check(!isset($vault->publicView()['shares']), 'public view has no shares');
});

test('release answers this nodes share', function (): void {
    [$store, $vault, $me, , $share] = setup();
    $api = new VaultApi($vault, $me, ['nodes' => []]);
    $r = call($api, 'GET', 'release', ['released' => true]);
    same(200, $r['status'], 'status');
    same(['x' => 2, 'y' => rtrim(strtr(base64_encode(substr($share, 1)), '+/', '-_'), '=')], $r['json']['share'], 'share');
    same(2, $r['json']['k'], 'k');
    same($me, $r['json']['node'], 'node');
    same(403, call($api, 'GET', 'release')['status'], 'not released');
});

test('release without a box is 404', function (): void {
    [, $vault, $me] = setup(true, false);
    $r = call(new VaultApi($vault, $me, ['nodes' => []]), 'GET', 'release', ['released' => true]);
    same(404, $r['status'], 'status');
    same(['error' => 'no share'], $r['json'], 'body');
    $r = call(new VaultApi($vault, null), 'GET', 'release', ['released' => true]);
    same(404, $r['status'], 'no node id');
});

test('release without shares is the legacy key', function (): void {
    [, $vault, $me] = setup(false);
    $r = call(new VaultApi($vault, $me, ['nodes' => []]), 'GET', 'release', ['released' => true]);
    same(['rk' => 'cmtleQ'], $r['json'], 'rk');
});

test('status lists nodes with a public url once shares exist', function (): void {
    [, $vault, $me, $other] = setup(false);
    $cluster = ['nodes' => [['id' => $me, 'public_url' => 'https://a.example'], ['id' => $other], ['id' => 'n_00000001', 'public_url' => 'https://c.example']]];
    $s = call(new VaultApi($vault, $me, $cluster), 'GET', 'status')['json'];
    check(!isset($s['k']) && !isset($s['nodes']), 'nothing without shares');
    [, $vault, $me, $other] = setup(true);
    $cluster = ['nodes' => [['id' => $me, 'public_url' => 'https://a.example'], ['id' => $other], ['id' => 'n_00000001', 'public_url' => 'https://c.example']]];
    $s = call(new VaultApi($vault, $me, $cluster), 'GET', 'status')['json'];
    same(2, $s['k'], 'k');
    same([['id' => $me, 'url' => 'https://a.example'], ['id' => 'n_00000001', 'url' => 'https://c.example']], $s['nodes'], 'nodes');
});

test('POST shares is owner plus csrf', function (): void {
    [, $vault, $me, $other] = setup(false);
    $api = new VaultApi($vault, $me, ['nodes' => []]);
    $body = json_encode(['k' => 2, 'n' => 2, 'cluster_version' => 1, 'boxes' => [$me => fakeBox(), $other => fakeBox()]]);
    same(401, call($api, 'POST', 'shares', ['body' => $body])['status'], 'anonymous');
    same(401, call($api, 'POST', 'shares', ['body' => $body, 'csrf' => true])['status'], 'no owner');
    same(403, call($api, 'POST', 'shares', ['body' => $body, 'owner' => true])['status'], 'no csrf');
    same(null, $vault->shares(), 'nothing saved yet');
    same(400, call($api, 'POST', 'shares', ['body' => '{"k":5}'] + OWN)['status'], 'invalid');
    same(200, call($api, 'POST', 'shares', ['body' => $body] + OWN)['status'], 'saved');
    same(2, $vault->shares()['k'], 'stored');
    same(null, $vault->releaseKey(), 'plain key gone');
});

finish();
