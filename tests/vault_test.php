<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Store, Vault};
require __DIR__ . '/lib.php';

function b(int $n): string { return rtrim(strtr(base64_encode(str_repeat('a', $n)), '+/', '-_'), '='); }
function sealed(): array { return ['iv' => b(12), 'ct' => b(20)]; }
function owner(): array { return ['salt' => b(16), 'iterations' => 1000, 'wrapped_rk' => sealed(), 'check' => sealed()]; }
function rec(string $id = 'r_0123456789abcdef'): array {
    return ['id' => $id, 'contact_id' => 'c_1', 'label' => 'Anna', 'code' => ['salt' => b(16), 'iv' => b(12), 'ct' => b(48)],
        'password' => null, 'owner_wrapped' => sealed()];
}
function pkg(array $rids = ['r_0123456789abcdef']): array {
    $w = []; foreach ($rids as $r) { $w[$r] = sealed(); }
    return ['id' => 'p_0123456789abcdef', 'label' => 'Docs', 'recipients' => $rids, 'owner_wrapped_ck' => sealed(), 'wraps' => $w,
        'manifest' => sealed(), 'files' => [['id' => 'f_0123456789abcdef', 'chunks' => 1, 'size' => 3]]];
}
function fresh(): Vault {
    $dir = sys_get_temp_dir() . '/dps-vault-' . bin2hex(random_bytes(5)); mkdir($dir, 0700);
    return new Vault(new Store($dir));
}
function throws(callable $f, string $class, string $what): void {
    try { $f(); } catch (\Throwable $e) { check($e instanceof $class, "$what: got " . get_class($e)); return; }
    check(false, "$what: nothing thrown");
}

test('fresh store reads empty', function (): void {
    same(['version' => 1, 'owner' => null, 'recipients' => [], 'packages' => []], fresh()->read(), 'shape');
});
test('initialise stores owner and release key once', function (): void {
    $v = fresh(); $v->initialise(owner(), 'RK_key-1');
    same('RK_key-1', $v->releaseKey(), 'rk');
    throws(fn() => $v->initialise(owner(), 'other'), \InvalidArgumentException::class, 'second init');
    same('RK_key-1', $v->releaseKey(), 'rk kept');
});
test('bad shapes are refused', function (): void {
    $v = fresh();
    $r = rec('R_0123456789ABCDEF');
    throws(fn() => $v->saveRecipient($r), \InvalidArgumentException::class, 'id');
    $r = rec(); $r['owner_wrapped']['iv'] = b(10);
    throws(fn() => $v->saveRecipient($r), \InvalidArgumentException::class, 'iv');
    $r = rec(); $r['label'] = str_repeat('x', 81);
    throws(fn() => $v->saveRecipient($r), \InvalidArgumentException::class, 'label');
    $o = owner(); $o['iterations'] = 0;
    throws(fn() => $v->initialise($o, 'k'), \InvalidArgumentException::class, 'iterations');
});
test('package recipients must exist', function (): void {
    $v = fresh();
    throws(fn() => $v->savePackage(pkg()), \InvalidArgumentException::class, 'unknown rid');
    $v->saveRecipient(rec()); $v->savePackage(pkg());
    same(1, count($v->read()['packages']), 'saved');
    $p = pkg(); $p['wraps']['r_ffffffffffffffff'] = sealed();
    throws(fn() => $v->savePackage($p), \InvalidArgumentException::class, 'stray wrap');
});
test('deleting a recipient drops it from packages', function (): void {
    $v = fresh(); $v->saveRecipient(rec()); $v->saveRecipient(rec('r_1111111111111111'));
    $v->savePackage(pkg(['r_0123456789abcdef', 'r_1111111111111111']));
    $v->deleteRecipient('r_0123456789abcdef');
    $p = $v->read()['packages'][0];
    same(['r_1111111111111111'], $p['recipients'], 'recipients');
    same(['r_1111111111111111'], array_keys($p['wraps']), 'wraps');
    same(1, count($v->read()['recipients']), 'recipient gone');
});
test('chunks are stored and found', function (): void {
    $v = fresh(); $v->putChunk('f_0123456789abcdef', 0, 'abc');
    $path = $v->chunkPath('f_0123456789abcdef', 0);
    same('abc', file_get_contents($path), 'content');
    same(null, $v->chunkPath('f_0123456789abcdef', 1), 'missing');
});
test('oversize chunk and path tricks are refused', function (): void {
    $v = fresh();
    throws(fn() => $v->putChunk('f_0123456789abcdef', 0, str_repeat('x', 4194369)), \LengthException::class, 'size');
    throws(fn() => $v->putChunk('f_../../etc', 0, 'x'), \InvalidArgumentException::class, 'path');
    throws(fn() => $v->chunkPath('../x', 0), \InvalidArgumentException::class, 'path read');
});
test('quota is enforced', function (): void {
    putenv('DPS_VAULT_MAX_BYTES=5000');
    try {
        $v = fresh(); $v->putChunk('f_0123456789abcdef', 0, 'abc');
        throws(fn() => $v->putChunk('f_0123456789abcdef', 1, 'def'), \LengthException::class, 'quota');
    } finally { putenv('DPS_VAULT_MAX_BYTES'); }
});
test('many tiny chunks cannot dodge the quota', function (): void {
    putenv('DPS_VAULT_MAX_BYTES=10000');
    try {
        $v = fresh();
        $v->putChunk('f_0123456789abcdef', 0, 'a');
        $v->putChunk('f_fedcba9876543210', 0, 'a');
        throws(fn() => $v->putChunk('f_0123456789abcdef', 1, 'a'), \LengthException::class, 'third tiny chunk');
        $v->putChunk('f_0123456789abcdef', 0, 'b');
    } finally { putenv('DPS_VAULT_MAX_BYTES'); }
});
test('public view hides owner data', function (): void {
    $v = fresh(); $v->initialise(owner(), 'k'); $v->saveRecipient(rec()); $v->savePackage(pkg());
    $json = json_encode($v->publicView());
    foreach (['owner', 'owner_wrapped', 'owner_wrapped_ck', 'contact_id'] as $k) {
        check(!str_contains($json, '"' . $k . '"'), "$k leaked");
    }
    same('r_0123456789abcdef', $v->publicView()['recipients'][0]['id'], 'recipient listed');
});
test('gc removes unreferenced old blobs', function (): void {
    $v = fresh(); $v->putChunk('f_0123456789abcdef', 0, 'abc');
    same(0, $v->gc(), 'recent kept');
    $dir = dirname($v->chunkPath('f_0123456789abcdef', 0));
    touch($dir, time() - 2 * 86400);
    same(1, $v->gc(), 'removed');
    same(null, $v->chunkPath('f_0123456789abcdef', 0), 'gone');
});
finish();
