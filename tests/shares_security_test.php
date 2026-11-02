<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Node, ShareBox, Store, Vault, VaultApi};
require __DIR__ . '/lib.php';

function sb(int $n): string { return rtrim(strtr(base64_encode(str_repeat('a', $n)), '+/', '-_'), '='); }
function sbSealed(): array { return ['iv' => sb(12), 'ct' => sb(20)]; }
function sbOwner(): array { return ['salt' => sb(16), 'iterations' => 1000, 'wrapped_rk' => sbSealed(), 'check' => sbSealed()]; }
function sbStore(): Store {
    $dir = sys_get_temp_dir() . '/dps-shs-' . bin2hex(random_bytes(5)); mkdir($dir, 0700);
    return new Store($dir);
}
function sbDer(string $curve): string {
    $k = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => $curve]);
    $pem = openssl_pkey_get_details($k)['key'];
    return (string)base64_decode((string)preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem));
}
function sbB64(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }
function sbCall(VaultApi $api, string $method, string $a, array $o = []): array {
    return $api->handle($o + ['method' => $method, 'a' => $a, 'query' => [], 'body' => '', 'owner' => false, 'csrf' => false, 'released' => false]);
}

test('the box opens only for its own node id', function (): void {
    $store = sbStore();
    $me = Node::init($store, 'me')['id'];
    $pk = $store->read('node')['share_pk'];
    $box = ShareBox::sealForTests($pk, $me, str_repeat("\x05", 33));
    same(str_repeat("\x05", 33), ShareBox::open($store, $me, $box), 'own id');
    try { ShareBox::open($store, 'n_00000000', $box); check(false, 'other id opened'); }
    catch (\RuntimeException) { check(true, 'other id refused'); }
});

test('each seal uses a fresh iv and ephemeral key', function (): void {
    $store = sbStore();
    $me = Node::init($store, 'me')['id'];
    $pk = $store->read('node')['share_pk'];
    $a = ShareBox::sealForTests($pk, $me, 'x');
    $b = ShareBox::sealForTests($pk, $me, 'x');
    check($a['iv'] !== $b['iv'], 'iv differs');
    check($a['epk'] !== $b['epk'], 'epk differs');
});

test('a bad epk is refused and the message does not leak details', function (): void {
    $store = sbStore();
    $me = Node::init($store, 'me')['id'];
    $pk = $store->read('node')['share_pk'];
    $good = ShareBox::sealForTests($pk, $me, str_repeat("\x05", 33));
    $der = (string)base64_decode(strtr($good['epk'], '-_', '+/'));
    $bad = [
        'p384' => sbB64(sbDer('secp384r1')),
        'garbage' => sbB64(str_repeat("\x01", 91)),
        'point not on curve' => sbB64(substr($der, 0, -1) . chr(ord($der[-1]) ^ 1)),
        'infinity' => sbB64(substr($der, 0, -65) . "\x04" . str_repeat("\x00", 64)),
        'truncated' => sbB64(substr($der, 0, 70)),
        'secp256k1' => sbB64(sbDer('secp256k1')),
    ];
    foreach ($bad as $name => $epk) {
        try {
            ShareBox::open($store, $me, ['epk' => $epk] + $good);
            check(false, "$name opened");
        } catch (\RuntimeException $e) {
            check(!str_contains($e->getMessage(), 'BEGIN') && strlen($e->getMessage()) < 60, "$name refused plainly");
        }
    }
    same(str_repeat("\x05", 33), ShareBox::open($store, $me, $good), 'good box still opens');
});

test('after saveShares no copy of the release key is left in the data folder', function (): void {
    $store = sbStore();
    $me = Node::init($store, 'me')['id'];
    $pk = $store->read('node')['share_pk'];
    $vault = new Vault($store);
    $rk = 'SECRETRELEASEKEYabc123';
    $vault->initialise(sbOwner(), $rk);
    // A second write of the release doc leaves a backup beside it.
    $store->write('release', ['rk' => $rk]);
    check(is_file($store->path('release') . '.bak'), 'backup exists before');
    $vault->saveShares(['k' => 1, 'n' => 1, 'cluster_version' => 1, 'boxes' => [$me => ShareBox::sealForTests($pk, $me, str_repeat("\x01", 33))]]);
    $dir = dirname($store->path('release'));
    $found = [];
    foreach (scandir($dir) as $f) {
        if (is_file("$dir/$f") && str_contains((string)file_get_contents("$dir/$f"), $rk)) { $found[] = $f; }
    }
    same([], $found, 'files still holding the key');
    same(null, $vault->releaseKey(), 'no key');
});

test('shares never show in the public view or status, and failures stay quiet', function (): void {
    $store = sbStore();
    $me = Node::init($store, 'me')['id'];
    $pk = $store->read('node')['share_pk'];
    $vault = new Vault($store);
    $vault->initialise(sbOwner(), 'cmtleQ');
    $other = 'n_' . bin2hex(random_bytes(4));
    $mine = ShareBox::sealForTests($pk, $me, str_repeat("\x02", 33));
    $vault->saveShares(['k' => 2, 'n' => 2, 'cluster_version' => 1, 'boxes' => [$me => $mine, $other => ShareBox::sealForTests($pk, 'n_00000000', str_repeat("\x03", 33))]]);
    $api = new VaultApi($vault, $me, ['nodes' => []]);
    $pub = json_encode(sbCall($api, 'GET', 'public', ['released' => true])['json']);
    $st = json_encode(sbCall($api, 'GET', 'status')['json']);
    foreach ([$mine['ct'], $mine['epk'], 'boxes'] as $needle) {
        check(!str_contains($pub, $needle) && !str_contains($st, $needle), "no $needle in public/status");
    }
    $rel = json_encode(sbCall($api, 'GET', 'release', ['released' => true])['json']);
    check(!str_contains($rel, $other) && !str_contains($rel, 'boxes'), 'release shows no other boxes');
    // The box for $other was sealed to this node's key but for another id: this node cannot open it as $other.
    $api2 = new VaultApi($vault, $other, ['nodes' => []]);
    $r = sbCall($api2, 'GET', 'release', ['released' => true]);
    same(404, $r['status'], 'unopenable box is 404');
    same(['error' => 'no share'], $r['json'], 'plain message');
    same(403, sbCall($api, 'GET', 'release')['status'], 'not released is 403 even with shares');
});

test('shares cannot be posted without the owner session and token', function (): void {
    $store = sbStore();
    $me = Node::init($store, 'me')['id'];
    $vault = new Vault($store);
    $vault->initialise(sbOwner(), 'cmtleQ');
    $api = new VaultApi($vault, $me, ['nodes' => []]);
    foreach ([[], ['csrf' => true], ['owner' => true]] as $o) {
        check(in_array(sbCall($api, 'POST', 'shares', ['body' => '{}'] + $o)['status'], [401, 403], true), 'refused');
    }
    same('cmtleQ', $vault->releaseKey(), 'key untouched');
});

finish();
