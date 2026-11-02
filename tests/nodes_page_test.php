<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Cluster, Node, Store};
require __DIR__ . '/lib.php';

const ROOT = __DIR__ . '/..';

function freshDir(): string
{
    $dir = sys_get_temp_dir() . '/dps-nodes-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700, true);
    return $dir;
}

function installed(string $dir): Store
{
    $s = new Store($dir);
    $s->write('config', [
        'installed_at' => 1_700_000_000, 'password_hash' => password_hash('correct horse', PASSWORD_DEFAULT),
        'base_url' => 'http://192.168.1.10:8080', 'timezone' => 'UTC', 'owner_name' => 'Ann',
    ]);
    return $s;
}

/** Runs a signed-in request in a child process; returns [stdout, exit]. */
function request(string $dir, string $query, ?array $post = null): array
{
    $script = sys_get_temp_dir() . '/dps-nodes-req-' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($script, '<?php
        $dir = getenv("T_DIR"); putenv("DPS_DATA=" . $dir);
        $_SERVER["SCRIPT_NAME"] = "/index.php";
        $_SERVER["REQUEST_METHOD"] = getenv("T_POST") !== false ? "POST" : "GET";
        $_GET = ["p" => getenv("T_PAGE")];
        session_save_path($dir);
        require getenv("T_ROOT") . "/app/bootstrap.php";
        DPS\Web::session();
        $cfg = DPS\store()->read("config");
        $_SESSION["owner"] = true; $_SESSION["pw"] = substr($cfg["password_hash"], -16); $_SESSION["csrf"] = "tok";
        if (getenv("T_POST") !== false) { $_POST = json_decode(getenv("T_POST"), true) + ["csrf" => "tok"]; }
        DPS\Web::page(getenv("T_PAGE"));
    ');
    $env = ['T_DIR' => $dir, 'T_ROOT' => realpath(ROOT), 'T_PAGE' => $query, 'PATH' => getenv('PATH') ?: ''];
    if ($post !== null) {
        $env['T_POST'] = json_encode($post);
    }
    $proc = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    $out = (string)stream_get_contents($pipes[1]) . (string)stream_get_contents($pipes[2]);
    $code = proc_close($proc);
    @unlink($script);
    return [$out, $code];
}

function cli(string $dir, array $args): array
{
    $cmd = array_merge([PHP_BINARY, realpath(ROOT) . '/cli/node.php'], $args);
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['DPS_DATA' => $dir, 'PATH' => getenv('PATH') ?: '']);
    $out = (string)stream_get_contents($pipes[1]) . (string)stream_get_contents($pipes[2]);
    return [$out, proc_close($proc)];
}

test('cli init, show and a second init refused', function (): void {
    $dir = freshDir();
    installed($dir);
    [$out, $code] = cli($dir, ['init', '--name=nas']);
    same(0, $code, 'init exit: ' . $out);
    [$out, $code] = cli($dir, ['show']);
    $id = json_decode($out, true);
    same(0, $code, 'show exit');
    same('nas', $id['name'] ?? null, 'show name');
    [, $code] = cli($dir, ['init', '--name=again']);
    check($code !== 0, 'second init fails');
});
test('cli join writes a two-node cluster with the primary at rank 0', function (): void {
    $pdir = freshDir();
    $primary = Node::init(new Store($pdir), 'nas');
    $dir = freshDir();
    installed($dir);
    cli($dir, ['init', '--name=pi']);
    [$out, $code] = cli($dir, ['join', '--primary-url=http://192.168.1.5:8080', '--primary=' . json_encode($primary)]);
    same(0, $code, 'join exit: ' . $out);
    $c = Cluster::read(new Store($dir));
    same(2, count($c['nodes']), 'two nodes');
    same($primary['id'], $c['primary'], 'primary');
    $self = Node::publicIdentity(new Store($dir));
    same(0, Cluster::rank(new Store($dir), $primary['id']), 'primary rank');
    same(1, Cluster::rank(new Store($dir), $self['id']), 'self rank');
    [$out, $code] = cli($dir, ['join', '--primary-url=ftp://x', '--primary=' . json_encode($primary)]);
    check($code !== 0, 'bad url refused: ' . $out);
    [$out, $code] = cli($dir, ['join', '--primary-url=http://192.168.1.5', '--primary={nope']);
    check($code !== 0, 'bad json refused');
});
test('the page shows this identity escaped and the add form on a primary', function (): void {
    $dir = freshDir();
    $s = installed($dir);
    Node::init($s, '<b>nas</b>');
    [$html, $code] = request($dir, 'nodes');
    check(str_contains($html, '&lt;b&gt;nas&lt;/b&gt;') && !str_contains($html, '<b>nas</b>'), 'escaped name: ' . substr($html, 0, 300));
    check(str_contains($html, 'value="node_add"'), 'add form');
    check(str_contains($html, 'name="csrf"'), 'csrf');
    check(!str_contains($html, 'sign_sk') && !str_contains($html, 'share_key_pem'), 'no private keys');
});
test('node_add makes this node rank 0 primary and adds the peer', function (): void {
    $dir = freshDir();
    $s = installed($dir);
    Node::init($s, 'nas');
    $peer = Node::init(new Store(freshDir()), 'pi');
    [$out] = request($dir, 'nodes', ['action' => 'node_add', 'identity' => json_encode($peer), 'url' => 'http://192.168.1.7:8080', 'rank' => '1']);
    $c = Cluster::read(new Store($dir));
    same(2, count($c['nodes']), 'two nodes: ' . $out);
    same(Node::publicIdentity($s)['id'], $c['primary'], 'self primary');
    same(1, Cluster::rank(new Store($dir), $peer['id']), 'peer rank');
    [$html] = request($dir, 'nodes', ['action' => 'node_add', 'identity' => '{bad', 'url' => 'http://192.168.1.8', 'rank' => '2']);
    same(2, count(Cluster::read(new Store($dir))['nodes']), 'bad identity ignored');
});
test('cluster_save and node_remove on the primary', function (): void {
    $dir = freshDir();
    $s = installed($dir);
    Node::init($s, 'nas');
    $peer = Node::init(new Store(freshDir()), 'pi');
    request($dir, 'nodes', ['action' => 'node_add', 'identity' => json_encode($peer), 'url' => 'http://192.168.1.7:8080', 'rank' => '1']);
    request($dir, 'nodes', ['action' => 'cluster_save', 'k' => '2', 'isolation_hours' => '24', 'step_delay_min' => '60']);
    $c = Cluster::read(new Store($dir));
    same([2, 24, 60], [$c['k'], $c['isolation_hours'], $c['step_delay_min']], 'saved');
    request($dir, 'nodes', ['action' => 'cluster_save', 'k' => '2', 'isolation_hours' => '5', 'step_delay_min' => '60']);
    same(24, Cluster::read(new Store($dir))['isolation_hours'], 'out of range ignored');
    request($dir, 'nodes', ['action' => 'node_remove', 'node_id' => $peer['id']]);
    $c = Cluster::read(new Store($dir));
    same(1, count($c['nodes']), 'removed');
    check($c['k'] <= 1, 'k lowered');
});
test('a secondary sees no edit forms and the other pages show the banner', function (): void {
    $pdir = freshDir();
    $primary = Node::init(new Store($pdir), 'Big <NAS>');
    $dir = freshDir();
    installed($dir);
    cli($dir, ['init', '--name=pi']);
    cli($dir, ['join', '--primary-url=http://192.168.1.5:8080', '--primary=' . json_encode($primary)]);
    [$html] = request($dir, 'nodes');
    check(!str_contains($html, 'value="node_add"') && !str_contains($html, 'value="cluster_save"') && !str_contains($html, 'value="node_remove"'), 'no forms');
    check(str_contains($html, 'This node mirrors Big &lt;NAS&gt;. Change settings there; check-ins here count everywhere.'), 'banner: ' . $html);
    [$html] = request($dir, 'switch');
    check(str_contains($html, 'This node mirrors'), 'banner on switch');
    [$ignored, $code] = request($dir, 'nodes', ['action' => 'node_add', 'identity' => json_encode($primary), 'url' => 'http://192.168.1.9', 'rank' => '3']);
    same(2, count(Cluster::read(new Store($dir))['nodes']), 'post refused on a secondary');
});
test('a single node shows no banner and a broken cluster.json does not break pages', function (): void {
    $dir = freshDir();
    $s = installed($dir);
    [$html] = request($dir, 'switch');
    check(!str_contains($html, 'This node mirrors'), 'no banner');
    file_put_contents($dir . '/cluster.json', '{broken');
    [$html] = request($dir, 'switch');
    check(str_contains($html, 'Switch'), 'page still renders');
    check(!str_contains($html, 'Fatal') && !str_contains($html, 'Uncaught'), 'no fatal');
});
finish();
