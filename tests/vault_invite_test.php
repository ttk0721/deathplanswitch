<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Store, Vault, VaultApi, Clock};
require __DIR__ . '/lib.php';

function b(int $n): string { return rtrim(strtr(base64_encode(str_repeat('a', $n)), '+/', '-_'), '='); }
function sealed(): array { return ['iv' => b(12), 'ct' => b(20)]; }
function rec(string $id = 'r_0123456789abcdef'): array {
    return ['id' => $id, 'contact_id' => 'c_1', 'label' => 'Anna', 'code' => ['salt' => b(16), 'iv' => b(12), 'ct' => b(48)],
        'password' => null, 'owner_wrapped' => sealed()];
}
function pw(int $it = 100000): array { return ['salt' => b(16), 'iterations' => $it, 'iv' => b(12), 'ct' => b(48)]; }
/** @return array{0: Vault, 1: VaultApi, 2: Store} */
function setup(): array {
    $dir = sys_get_temp_dir() . '/dps-vinv-' . bin2hex(random_bytes(5)); mkdir($dir, 0700);
    $store = new Store($dir);
    $v = new Vault($store);
    $v->saveRecipient(rec());
    return [$v, new VaultApi($v), $store];
}
function call(VaultApi $api, string $method, string $a, array $o = []): array {
    return $api->handle($o + ['method' => $method, 'a' => $a, 'query' => [], 'body' => '', 'owner' => false, 'csrf' => false, 'released' => false]);
}
const RID = 'r_0123456789abcdef';

test('issue then inviteRecipient returns only id and code', function (): void {
    [$v, , $store] = setup();
    $t = $v->issueInvite(RID);
    $r = $v->inviteRecipient($t);
    same(['id', 'code'], array_keys($r), 'keys');
    same(RID, $r['id'], 'id');
    same(null, $v->inviteRecipient('nope'), 'wrong token');
    $t2 = $v->issueInvite(RID);
    same(null, $v->inviteRecipient($t), 'old replaced');
    check($v->inviteRecipient($t2) !== null, 'new works');
    check(!str_contains(json_encode($store->read('vault-invites', [])), $t2), 'plain token not stored');
});
test('setPassword stores it and the token then fails', function (): void {
    [$v, $api] = setup();
    $t = $v->issueInvite(RID);
    $r = call($api, 'POST', 'password', ['query' => ['t' => $t], 'body' => json_encode(['password' => pw()])]);
    same(200, $r['status'], 'ok');
    same(100000, $v->read()['recipients'][0]['password']['iterations'], 'stored');
    same(404, call($api, 'GET', 'invite', ['query' => ['t' => $t]])['status'], 'get after use');
    same(404, call($api, 'POST', 'password', ['query' => ['t' => $t], 'body' => json_encode(['password' => pw()])])['status'], 'post after use');
});
test('invite routes: shape and method matrix', function (): void {
    [$v, $api] = setup();
    $t = $v->issueInvite(RID);
    $r = call($api, 'GET', 'invite', ['query' => ['t' => $t]]);
    same(200, $r['status'], 'get');
    same(['recipient'], array_keys($r['json']), 'shape');
    same(['id', 'code'], array_keys($r['json']['recipient']), 'recipient keys');
    same(404, call($api, 'GET', 'invite')['status'], 'no token');
    same(404, call($api, 'GET', 'invite', ['query' => ['t' => 'zzz']])['status'], 'bad token');
    same(404, call($api, 'POST', 'invite', ['query' => ['t' => $t]])['status'], 'wrong method');
    same(404, call($api, 'GET', 'password', ['query' => ['t' => $t]])['status'], 'wrong method 2');
    same(404, call($api, 'POST', 'password', ['body' => json_encode(['password' => pw()])])['status'], 'no token post');
});
test('expired token gives null', function (): void {
    [$v] = setup();
    $t = $v->issueInvite(RID);
    Clock::advance(31 * 86400);
    same(null, $v->inviteRecipient($t), 'expired');
});
test('bad password shape gives 400 and keeps the token', function (): void {
    [$v, $api] = setup();
    $t = $v->issueInvite(RID);
    foreach ([99999, 20000001] as $it) {
        same(400, call($api, 'POST', 'password', ['query' => ['t' => $t], 'body' => json_encode(['password' => pw($it)])])['status'], "iter $it");
    }
    same(400, call($api, 'POST', 'password', ['query' => ['t' => $t], 'body' => '{}'])['status'], 'missing');
    same(400, call($api, 'POST', 'password', ['query' => ['t' => $t], 'body' => 'x'])['status'], 'not json');
    check($v->inviteRecipient($t) !== null, 'token kept');
    same(null, $v->read()['recipients'][0]['password'], 'unchanged');
});
test('recipient_delete drops the invite', function (): void {
    [$v, $api, $store] = setup();
    $t = $v->issueInvite(RID);
    same(200, call($api, 'POST', 'recipient_delete', ['owner' => true, 'csrf' => true, 'body' => json_encode(['id' => RID])])['status'], 'delete');
    same(404, call($api, 'GET', 'invite', ['query' => ['t' => $t]])['status'], 'gone');
    same([], $store->read('vault-invites', []), 'invite doc empty');
});
test('issueInvite for an unknown recipient throws', function (): void {
    [$v] = setup();
    try { $v->issueInvite('r_ffffffffffffffff'); check(false, 'no throw'); } catch (\InvalidArgumentException) { check(true, 'threw'); }
});
finish();
