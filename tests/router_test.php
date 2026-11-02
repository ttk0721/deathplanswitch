<?php
/**
 * bin/router.php: the private-path rules of the built-in server, tried against
 * a real `php -S`. Requests go over a raw socket so nothing normalises the path
 * before the router sees it.
 */
declare(strict_types=1);
namespace DPS\Tests;
require __DIR__ . '/lib.php';

$root = dirname(__DIR__);
// A data folder that sits inside assets/ must stay private too.
$inside = $root . '/assets/zz-router-data';
mkdir($inside, 0700, true);
file_put_contents($inside . '/config.json', '{"secret":1}');
file_put_contents($inside . '/note.txt', 'secret');

$probe = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);

$proc = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root, $root . '/bin/router.php'],
    [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes,
    $root,
    ['DPS_DATA' => $inside, 'PATH' => (string) getenv('PATH')]
);
register_shutdown_function(function () use ($proc, $inside): void {
    proc_terminate($proc);
    proc_close($proc);
    @unlink($inside . '/config.json');
    @unlink($inside . '/note.txt');
    @rmdir($inside);
    @unlink(dirname($inside) . '/zz-link.php');
    @unlink(dirname($inside) . '/zz-out.css');
});
for ($i = 0; $i < 100; $i++) {
    $s = @fsockopen('127.0.0.1', $port);
    if ($s) {
        fclose($s);
        break;
    }
    usleep(50000);
}

/** @return array{0:int,1:string,2:string} status, headers, body */
function req(string $target, string $method = 'GET'): array
{
    global $port;
    $s = fsockopen('127.0.0.1', $port, $en, $es, 5);
    stream_set_timeout($s, 10);
    fwrite($s, "$method $target HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
    $raw = (string) stream_get_contents($s);
    fclose($s);
    [$head, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
    preg_match('#^HTTP/\S+ (\d+)#', $head, $m);
    return [(int) ($m[1] ?? 0), $head, $body];
}

function status(string $target): int
{
    return req($target)[0];
}

test('the front page and the installer are served', function (): void {
    $r = req('/'); check(in_array($r[0], [200, 302, 303], true), '/ gave ' . $r[0] . substr($r[2], 0, 300));
    check(in_array(status('/install.php'), [200, 302, 303], true), '/install.php is served');
    // health.php answers 404 itself before the first setup; what matters is that the router let it run.
    check(strpos(req('/health.php')[2], "Not found\n") !== 0, '/health.php reached the script');
});
test('static assets are served with their type', function (): void {
    [$st, $head] = req('/assets/app.css');
    same(200, $st, 'app.css');
    check(stripos($head, 'content-type: text/css') !== false, 'text/css: ' . $head);
    same(200, status('/assets/vault/crypto.js'), 'nested asset');
});
test('private folders and project files are 403', function (): void {
    foreach (['/app/bootstrap.php', '/data/', '/data/config.json', '/lang/pl/core.php', '/cli/x.php', '/tests/run.php',
        '/docs/pl/instalacja.md', '/bin/router.php', '/docker/entrypoint.sh', '/packaging/build.sh', '/README.md',
        '/VERSION', '/.git/config', '/.env', '/docker-compose.yml', '/Dockerfile', '/x.sh', '/x.ps1', '/x.bak', '/x.log',
        '/.env.example', '/a.json', '/assets/x.json', '/config.local.php'] as $p) {
        same(403, status($p), $p);
    }
});
test('bypass: upper and mixed case', function (): void {
    same(403, status('/APP/x'), '/APP/x');
    same(403, status('/Data/config.json'), '/Data');
    same(403, status('/readme.MD'), 'extension case');
});
test('bypass: percent-encoded names and slashes', function (): void {
    same(403, status('/%61pp/bootstrap.php'), '%61pp');
    same(403, status('/app%2fbootstrap.php'), 'encoded slash');
    same(403, status('/%41PP%2Fbootstrap.php'), 'encoded upper');
});
test('bypass: double encoding never decodes twice', function (): void {
    foreach (['/%2561pp/bootstrap.php', '/assets/%252e%252e/app/bootstrap.php', '/%252e%252e/app/bootstrap.php',
        '/assets/%252e%252e%252fapp%252fbootstrap.php'] as $p) {
        $r = req($p);
        check(in_array($r[0], [400, 403, 404], true), "$p gave {$r[0]}");
        check(strpos($r[2], 'DPS_ROOT') === false, "$p leaked code");
    }
});
test('bypass: doubled and extra slashes', function (): void {
    same(403, status('//app/x'), '//app');
    same(403, status('///app//bootstrap.php'), '///app');
    same(403, status('/./app/bootstrap.php'), '/./app');
});
test('bypass: trailing dots and spaces on a segment', function (): void {
    same(403, status('/app./x'), '/app./x');
    same(403, status('/app../x'), '/app../x');
    same(403, status('/app%20/x'), '/app%20/x');
    same(403, status('/app%20./x'), '/app%20./x');
    same(403, status('/README.md.'), 'README.md.');
    same(403, status('/README.md%20'), 'README.md%20');
    same(403, status('/data%20/config.json'), 'data%20');
});
test('bypass: dot-dot segments are 400', function (): void {
    same(400, status('/assets/../app/bootstrap.php'), 'plain');
    same(400, status('/assets/%2e%2e/app/bootstrap.php'), 'encoded dots');
    same(400, status('/assets/..%2fapp%2fbootstrap.php'), 'encoded slash');
    same(400, status('/assets/.%2e/app/bootstrap.php'), 'half encoded');
    same(400, status('/assets/.../x'), 'three dots');
    same(400, status('/assets/../'), 'trailing');
});
test('bypass: backslashes count as slashes', function (): void {
    same(403, status('/app%5Cbootstrap.php'), 'encoded backslash');
    same(403, status('/%5Capp/bootstrap.php'), 'leading backslash');
    same(400, status('/assets%5C..%5Capp%5Cbootstrap.php'), 'backslash dot-dot');
});
test('bypass: NUL bytes and control characters are 400', function (): void {
    same(400, status('/index.php%00.css'), 'encoded NUL');
    same(400, status('/assets/app.css%00'), 'NUL at the end');
    same(400, status('/app%00/x'), 'NUL in a folder');
    same(400, status('/assets/app%0d%0a.css'), 'CRLF');
});
test('bypass: stream and short-name tricks', function (): void {
    same(400, status('/index.php::$DATA'), 'alternate data stream');
    check(in_array(status('/INDEX~1.PHP'), [403, 404], true), '8.3 name');
});
test('bypass: path info after an entry point is not served', function (): void {
    same(404, status('/index.php/x'), '/index.php/x');
    same(404, status('/index.php/app/bootstrap.php'), 'path info with app');
    same(404, status('//index.php'), '//index.php');
    same(404, status('/index.php/'), '/index.php/');
});
test('a data folder under the docroot is not served', function (): void {
    foreach (['/assets/zz-router-data/config.json', '/assets/zz-router-data/note.txt', '/assets/zz-router-data/', '/assets/zz-router-data'] as $p) {
        $r = req($p);
        check(in_array($r[0], [403, 404], true), "$p gave {$r[0]}");
        check(strpos($r[2], 'secret') === false, "$p leaked");
    }
});
test('hardening: absolute-URI targets, wildcards, symlinks', function (): void {
    foreach (['http://127.0.0.1/app/bootstrap.php', 'http://127.0.0.1/assets/app.css', '*'] as $p) {
        $r = req($p);
        check(in_array($r[0], [400, 403, 404], true) && strpos($r[2], 'DPS_ROOT') === false, "$p gave {$r[0]}");
    }
    same(400, status('/assets/app%3F.css'), 'encoded question mark');
    same(400, status('/index.ph%3C'), 'DOS wildcard');
    // A .php name that links to a harmless file must not be executed.
    $dir = __DIR__ . '/../assets';
    file_put_contents($dir . '/zz-out.css', '<?php echo "pwned";');
    if (@symlink($dir . '/zz-out.css', $dir . '/zz-link.php')) {
        $r = req('/assets/zz-link.php');
        same(404, $r[0], 'php-named symlink');
        check(strpos($r[2], 'pwned') === false, 'symlink executed');
    }
});
test('there are no directory listings', function (): void {
    foreach (['/assets/', '/assets', '/assets/vault/', '/assets/zz-router-data/'] as $p) {
        $r = req($p);
        same(404, $r[0], $p);
        check(stripos($r[2], 'Index of') === false, "$p listed");
    }
});
test('php files outside the allow-list are 404', function (): void {
    same(404, status('/nope.php'), '/nope.php');
    same(404, status('/cron.php'), '/cron.php');
    same(404, status('/assets/evil.php'), 'php under assets');
    same(404, status('/nope.txt'), 'other file');
    same(404, status('/composer.lock'), 'unknown');
});
test('denials carry nosniff', function (): void {
    foreach (['/app/x', '/assets/../x', '/nope.php'] as $p) {
        check(stripos(req($p)[1], 'x-content-type-options: nosniff') !== false, "$p nosniff");
    }
});
test('other methods follow the same rules', function (): void {
    same(403, req('/app/bootstrap.php', 'POST')[0], 'POST');
    same(403, req('/app/bootstrap.php', 'HEAD')[0], 'HEAD');
});
finish();
