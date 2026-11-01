<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Cluster, Node, Store};
require __DIR__ . '/lib.php';

function cronStore(): Store {
    $dir = sys_get_temp_dir() . '/dps-cron-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700, true);
    $s = new Store($dir);
    $s->write('config', [
        'owner_name' => 'Sam Rivers', 'owner_email' => 'owner@example.test',
        'backup_email' => 'owner@backup.test', 'timezone' => 'Australia/Sydney',
        'slot_dow' => 1, 'slot_hour' => 9, 'base_url' => 'https://switch.example.test',
        'smtp' => ['from_email' => 'switch@example.test'], 'installed_at' => time(),
    ]);
    return $s;
}
function entry(array $id, int $rank, string $url): array {
    return ['id' => $id['id'], 'name' => $id['name'], 'url' => $url,
        'sign_pk' => $id['sign_pk'], 'share_pk' => $id['share_pk'], 'rank' => $rank];
}
function runCron(Store $s): void {
    $proc = proc_open([PHP_BINARY, 'cron.php', '--verbose'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes, dirname(__DIR__), array_merge(getenv(), ['DPS_DATA' => $s->dir()]));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    $code = proc_close($proc);
    same(0, $code, 'cron exits 0: ' . $out);
    foreach (['Fatal', 'Warning', 'Deprecated'] as $bad) {
        check(!str_contains($out, $bad), "no $bad in output: $out");
    }
}

test('cron runs the plain engine without a node identity', function (): void {
    runCron(cronStore());
});
test('cron runs the plain engine with federation off', function (): void {
    $s = cronStore();
    $me = Node::init($s, 'a');
    Cluster::save($s, ['k' => 1, 'primary' => $me['id'], 'nodes' => [entry($me, 0, 'https://a.example.org')]]);
    check(!Cluster::enabled($s), 'one node is not federated');
    runCron($s);
});
test('cron runs the federated engine with an unreachable peer', function (): void {
    $s = cronStore();
    $me = Node::init($s, 'a');
    $other = Node::init(cronStore(), 'b');
    Cluster::save($s, ['k' => 1, 'primary' => $me['id'], 'nodes' => [
        entry($me, 0, 'https://a.example.org'), entry($other, 1, 'https://b.example.org')]]);
    check(Cluster::enabled($s), 'two nodes is federated');
    runCron($s);
});
finish();
