<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Clock, Node, Store};
require __DIR__ . '/lib.php';

function nodeStore(): Store
{
    $dir = sys_get_temp_dir() . '/dps-node-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700, true);
    return new Store($dir);
}

test('init gives the identity shape and a 0600 node.json', function (): void {
    $s = nodeStore();
    check(Node::publicIdentity($s) === null, 'no identity yet');
    $id = Node::init($s, 'nas');
    same(['id', 'name', 'sign_pk', 'share_pk'], array_keys($id), 'keys');
    check((bool)preg_match('/^n_[0-9a-f]{8}$/', $id['id']), 'id format');
    same('nas', $id['name'], 'name');
    same(43, strlen($id['sign_pk']), 'sign_pk length');
    same($id, Node::publicIdentity($s), 'publicIdentity');
    // Windows has no POSIX modes; its protection is the data folder ACL set by install.ps1
    if (PHP_OS_FAMILY !== 'Windows') { same('0600', substr(sprintf('%o', fileperms($s->path('node'))), -4), 'mode'); }
    $raw = $s->read('node');
    same(64, strlen(base64_decode(strtr($raw['sign_sk'], '-_', '+/'))), 'sk bytes');
});
test('a second init throws', function (): void {
    $s = nodeStore();
    Node::init($s, 'a');
    $threw = false;
    try { Node::init($s, 'b'); } catch (\Throwable) { $threw = true; }
    check($threw, 'second init refused');
});
test('canonical matches the documented form', function (): void {
    same(
        "DPS1\nGET\n/node-api.php?a=state\n1800000000\nab\ne3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
        Node::canonical('GET', '/node-api.php?a=state', '', 1800000000, 'ab'),
        'canonical'
    );
});
test('sign and verify', function (): void {
    $s = nodeStore();
    $id = Node::init($s, 'a');
    $other = Node::init(nodeStore(), 'b');
    $c = Node::canonical('POST', '/x', '{}', 5, 'cd');
    $sig = Node::sign($s, $c);
    check(Node::verify($id['sign_pk'], $c, $sig), 'valid');
    $flipped = $c;
    $flipped[3] = $flipped[3] === 'X' ? 'Y' : 'X';
    check(!Node::verify($id['sign_pk'], $flipped, $sig), 'flipped');
    check(!Node::verify($other['sign_pk'], $c, $sig), 'other key');
    check(!Node::verify($id['sign_pk'], $c, '!!notbase64'), 'malformed sig');
    check(!Node::verify($id['sign_pk'], $c, ''), 'empty sig');
    check(!Node::verify('short', $c, $sig), 'malformed pk');
    check(!Node::verify($id['sign_pk'], $c, 'AAAA'), 'short sig');
});
test('seenNonce records and expires', function (): void {
    $s = nodeStore();
    Clock::set(1_800_000_000);
    check(!Node::seenNonce($s, 'n_00000001', 'aa', Clock::now()), 'first');
    check(Node::seenNonce($s, 'n_00000001', 'aa', Clock::now()), 'second');
    check(!Node::seenNonce($s, 'n_00000002', 'aa', Clock::now()), 'other node');
    Clock::advance(601);
    check(!Node::seenNonce($s, 'n_00000001', 'aa', Clock::now()), 'after expiry');
    Clock::set(null);
});
test('share_pk imports as an SPKI public key', function (): void {
    $s = nodeStore();
    $id = Node::init($s, 'a');
    $der = base64_decode(strtr($id['share_pk'], '-_', '+/'));
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    $k = openssl_pkey_get_public($pem);
    check($k !== false, 'imports');
    same(OPENSSL_KEYTYPE_EC, openssl_pkey_get_details($k)['type'], 'EC');
});
finish();
