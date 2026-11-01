<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Cluster, Store};
require __DIR__ . '/lib.php';

function cstore(): Store {
    $dir = sys_get_temp_dir() . '/dps-cluster-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700);
    return new Store($dir);
}
function node(string $hex, int $rank, string $url = 'https://a.example.org'): array {
    return ['id' => 'n_' . $hex, 'name' => 'Node ' . $hex, 'url' => $url,
        'sign_pk' => rtrim(strtr(base64_encode(str_repeat('a', 32)), '+/', '-_'), '='),
        'share_pk' => 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE', 'rank' => $rank];
}
function good(array $nodes): array {
    return ['version' => 0, 'k' => 1, 'primary' => null, 'isolation_hours' => 48, 'step_delay_min' => 30, 'nodes' => $nodes];
}
function refuses(Store $s, array $c, string $what): void {
    try { Cluster::save($s, $c); } catch (\InvalidArgumentException $e) { check($e->getMessage() !== '', $what . ' has reason'); return; }
    check(false, $what . ' should throw');
}

test('defaults', function (): void {
    $s = cstore();
    $c = Cluster::read($s);
    same(0, $c['version'], 'version');
    same(1, $c['k'], 'k');
    same(null, $c['primary'], 'primary');
    same(48, $c['isolation_hours'], 'isolation');
    same(30, $c['step_delay_min'], 'delay');
    same([], $c['nodes'], 'nodes');
    same(false, Cluster::enabled($s), 'disabled');
});
test('validation refuses bad input', function (): void {
    $s = cstore();
    $bad = node('0a1b2c3d', 0); $bad['id'] = 'n_xyz';
    refuses($s, good([$bad]), 'bad id');
    refuses($s, good([node('0a1b2c3d', 0, 'ftp://192.168.1.5')]), 'ftp');
    refuses($s, good([node('0a1b2c3d', 0, 'http://example.org')]), 'public http');
    refuses($s, good([node('0a1b2c3d', 1), node('1a1b2c3d', 1)]), 'duplicate rank');
    $c = good([node('0a1b2c3d', 0), node('1a1b2c3d', 1)]); $c['k'] = 3;
    refuses($s, $c, 'k > nodes');
    $c = good([node('0a1b2c3d', 0)]); $c['primary'] = 'n_ffffffff';
    refuses($s, $c, 'unknown primary');
    $c = good([node('0a1b2c3d', 0)]); $c['isolation_hours'] = 5;
    refuses($s, $c, 'isolation');
    $c = good([node('0a1b2c3d', 0)]); $c['step_delay_min'] = 5;
    refuses($s, $c, 'delay');
    refuses($s, good([node('0a1b2c3d', 10)]), 'rank 10');
    $n = node('0a1b2c3d', 0); $n['sign_pk'] = 'AAAA';
    refuses($s, good([$n]), 'short sign_pk');
});
test('private and tailscale http accepted', function (): void {
    $s = cstore();
    Cluster::save($s, good([node('0a1b2c3d', 0, 'http://192.168.1.5:8080'), node('1a1b2c3d', 1, 'http://100.101.1.2')]));
    same(2, count(Cluster::read($s)['nodes']), 'saved');
    Cluster::save($s, good([node('0a1b2c3d', 0, 'http://box.ts.net'), node('1a1b2c3d', 1, 'http://pi.local')]));
    check(true, 'names ok');
});
test('version increments', function (): void {
    $s = cstore();
    Cluster::save($s, good([]));
    same(1, Cluster::read($s)['version'], 'v1');
    Cluster::save($s, good([]));
    same(2, Cluster::read($s)['version'], 'v2');
});
test('peers, find, enabled', function (): void {
    $s = cstore();
    Cluster::save($s, good([node('0a1b2c3d', 0), node('1a1b2c3d', 1), node('2a1b2c3d', 2)]));
    same(true, Cluster::enabled($s), 'enabled');
    $p = Cluster::peers($s, 'n_1a1b2c3d');
    same(2, count($p), 'two peers');
    foreach ($p as $x) { check($x['id'] !== 'n_1a1b2c3d', 'self excluded'); }
    same('n_1a1b2c3d', Cluster::selfEntry($s, 'n_1a1b2c3d')['id'], 'selfEntry');
    same('n_2a1b2c3d', Cluster::find($s, 'n_2a1b2c3d')['id'], 'find');
    same(null, Cluster::find($s, 'n_ffffffff'), 'find none');
});
test('rank', function (): void {
    $s = cstore();
    same(0, Cluster::rank($s, 'n_0a1b2c3d'), 'absent');
    Cluster::save($s, good([node('0a1b2c3d', 0)]));
    same(0, Cluster::rank($s, 'n_0a1b2c3d'), 'single');
    Cluster::save($s, good([node('0a1b2c3d', 0), node('1a1b2c3d', 3)]));
    same(3, Cluster::rank($s, 'n_1a1b2c3d'), 'rank 3');
    same(0, Cluster::rank($s, 'n_ffffffff'), 'absent in cluster');
});
test('isPrimary', function (): void {
    $s = cstore();
    same(true, Cluster::isPrimary($s, 'n_bbbbbbbb'), 'no cluster');
    $c = good([node('aaaaaaaa', 0), node('bbbbbbbb', 1)]); $c['primary'] = 'n_aaaaaaaa';
    Cluster::save($s, $c);
    same(false, Cluster::isPrimary($s, 'n_bbbbbbbb'), 'secondary');
    same(true, Cluster::isPrimary($s, 'n_aaaaaaaa'), 'primary');
});
finish();
