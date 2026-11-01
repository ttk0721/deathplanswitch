<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Cluster, Store};
require __DIR__ . '/lib.php';

function pstore(): Store {
    $dir = sys_get_temp_dir() . '/dps-pub-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700);
    return new Store($dir);
}
function pnode(string $hex, int $rank, ?string $pub): array {
    $n = ['id' => 'n_' . $hex, 'name' => 'Node ' . $hex, 'url' => 'https://a.example.org',
        'sign_pk' => rtrim(strtr(base64_encode(str_repeat('a', 32)), '+/', '-_'), '='),
        'share_pk' => 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE', 'rank' => $rank];
    if ($pub !== null) {
        $n['public_url'] = $pub;
    }
    return $n;
}
function pcluster(array $nodes): array {
    return ['version' => 0, 'k' => 1, 'primary' => null, 'isolation_hours' => 48, 'step_delay_min' => 30, 'nodes' => $nodes];
}
function prefuses(string $pub): void {
    try { Cluster::save(pstore(), pcluster([pnode('0a1b2c3d', 0, $pub)])); } catch (\InvalidArgumentException $e) { check(true, $pub); return; }
    check(false, $pub . ' should throw');
}

test('valid https public_url is accepted and stored', function (): void {
    $s = pstore();
    Cluster::save($s, pcluster([pnode('0a1b2c3d', 0, 'https://n1.deadman.example')]));
    same('https://n1.deadman.example', Cluster::read($s)['nodes'][0]['public_url'], 'stored');
});
test('missing public_url is fine', function (): void {
    $s = pstore();
    Cluster::save($s, pcluster([pnode('0a1b2c3d', 0, null)]));
    check(!isset(Cluster::read($s)['nodes'][0]['public_url']), 'absent');
    same([], Cluster::publicOrigins($s), 'no origins');
});
test('http, userinfo, query, fragment are refused', function (): void {
    putenv('DPS_TEST_ALLOW_HTTP_PUBLIC');
    prefuses('http://n1.deadman.example');
    prefuses('https://user:pw@n1.deadman.example');
    prefuses('https://n1.deadman.example/?a=1');
    prefuses('https://n1.deadman.example/#x');
    prefuses('https://');
    prefuses('ftp://n1.deadman.example');
});
test('http allowed only with the test env', function (): void {
    putenv('DPS_TEST_ALLOW_HTTP_PUBLIC=1');
    $s = pstore();
    Cluster::save($s, pcluster([pnode('0a1b2c3d', 0, 'http://127.0.0.1:8081')]));
    same(['http://127.0.0.1:8081'], Cluster::publicOrigins($s), 'origin');
    putenv('DPS_TEST_ALLOW_HTTP_PUBLIC');
});
test('publicOrigins gives unique scheme://host[:port]', function (): void {
    $s = pstore();
    Cluster::save($s, pcluster([
        pnode('0a1b2c3d', 0, 'https://n1.deadman.example/vault/'),
        pnode('0a1b2c3e', 1, 'https://n1.deadman.example'),
        pnode('0a1b2c3f', 2, 'https://N2.deadman.example:8443'),
        pnode('0a1b2c40', 3, null),
    ]));
    same(['https://n1.deadman.example', 'https://n2.deadman.example:8443'], Cluster::publicOrigins($s), 'origins');
});
finish();
