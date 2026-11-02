<?php
/**
 * The front-page background: upload validation, storage, and the endpoint
 * that serves it (against a real `php -S` behind bin/router.php).
 */
declare(strict_types=1);
namespace DPS\Tests;

use DPS\{Landing, Store};

require __DIR__ . '/lib.php';

\DPS\I18n::setLanguage('en');
$root = dirname(__DIR__);

function dataDir(): string
{
    $dir = sys_get_temp_dir() . '/dps-landing-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700, true);
    return $dir;
}
function sample(string $kind): string
{
    $b64 = [
        'png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        'jpg' => '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=',
        'webp' => 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==',
        'gif' => 'R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==',
    ][$kind];
    return (string) base64_decode($b64);
}
function file_with(string $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'dps-up-');
    file_put_contents($path, $bytes);
    return $path;
}

test('JPEG, PNG and WebP are accepted, by their contents', function (): void {
    foreach (['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'] as $ext => $mime) {
        $r = Landing::inspect(file_with(sample($ext)));
        check(is_array($r) && $r['ext'] === $ext && $r['mime'] === $mime, "$ext: " . json_encode($r));
    }
});
test('a renamed file is refused: a script, text, a GIF, SVG', function (): void {
    foreach ([
        'php' => '<?php system($_GET["c"]);',
        'text' => "hello\n",
        'gif' => sample('gif'),
        'svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        'empty' => '',
    ] as $label => $bytes) {
        check(is_string(Landing::inspect(file_with($bytes))), "$label refused");
    }
});
test('a polyglot is refused: an image header with a script inside, or a header with no image', function (): void {
    check(is_string(Landing::inspect(file_with(sample('png') . "\n<?php echo 1;"))), 'PNG + PHP');
    check(is_string(Landing::inspect(file_with(sample('jpg') . '<script>alert(1)</script>'))), 'JPEG + script');
    check(is_string(Landing::inspect(file_with("\xFF\xD8\xFF\xE0" . str_repeat('A', 64)))), 'JPEG magic, not a JPEG');
    check(is_string(Landing::inspect(file_with("\x89PNG\r\n\x1A\n" . 'not really'))), 'PNG magic, not a PNG');
    check(is_string(Landing::inspect(file_with("RIFF\x04\x00\x00\x00WEBPjunk"))), 'WebP magic, not a WebP');
    // GIF bytes cannot hide behind a PNG signature check: the type reported must match the magic.
    check(is_string(Landing::inspect(file_with(sample('gif')))), 'GIF');
});
test('the size limit is 3 MB', function (): void {
    $ok = sample('png') . str_repeat("\0", 3 * 1024 * 1024 - strlen(sample('png')));
    check(is_array(Landing::inspect(file_with($ok))), 'exactly 3 MB passes the size check');
    $big = file_with($ok . "\0");
    $r = Landing::inspect($big);
    check(is_string($r) && str_contains($r, '3 MB'), 'one byte over: ' . json_encode($r));
});
test('stored under a random name, mode 0600; the old file goes on replace', function (): void {
    $store = new Store(dataDir());
    $a = Landing::store($store, file_with(sample('png')), 'png', null);
    check(preg_match('/^background-[a-f0-9]{16}\.png$/D', $a) === 1, "name $a");
    $path = Landing::imagePath($store, $a);
    check($path !== null && is_file($path), 'stored');
    same('0600', substr(sprintf('%o', fileperms((string) $path)), -4), 'mode');
    $b = Landing::store($store, file_with(sample('jpg')), 'jpg', $a);
    check($a !== $b && Landing::imagePath($store, $a) === null && Landing::imagePath($store, $b) !== null, 'old removed, new kept');
    same('image/jpeg', Landing::mime($b), 'mime');
    same(1, count(glob(Landing::dir($store) . '/*') ?: []), 'one file left');
});
test('only names we made are ever served', function (): void {
    $store = new Store(dataDir());
    foreach (['../config.json', 'background-../x.png', 'background-0123456789abcdef.php', 'config.json', '/etc/passwd', null, 5] as $name) {
        same(null, Landing::imagePath($store, $name), 'path for ' . var_export($name, true));
    }
    same(null, Landing::mime('background-0123456789abcdef.svg'), 'svg has no type');
});

// ----------------------------------------------------------------- the endpoint

$dir = dataDir();
$store = new Store($dir);
$name = Landing::store($store, file_with(sample('png')), 'png', null);
$store->write('config', ['installed_at' => 1700000000, 'owner_name' => 'Ann', 'landing' => ['background' => 'own', 'image' => $name]]);
file_put_contents($dir . '/secret.txt', 'SECRET');

$probe = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
$proc = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root, $root . '/bin/router.php'],
    [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes,
    $root,
    ['DPS_DATA' => $dir, 'PATH' => (string) getenv('PATH')]
);
register_shutdown_function(function () use ($proc): void {
    proc_terminate($proc);
    proc_close($proc);
});
for ($i = 0; $i < 100; $i++) {
    $s = @fsockopen('127.0.0.1', $port);
    if ($s) {
        fclose($s);
        break;
    }
    usleep(50000);
}
/** @return array{0:int,1:string,2:string} */
function req(string $target): array
{
    global $port;
    $s = fsockopen('127.0.0.1', $port, $en, $es, 5);
    stream_set_timeout($s, 10);
    fwrite($s, "GET $target HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
    $raw = (string) stream_get_contents($s);
    fclose($s);
    [$head, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
    preg_match('#^HTTP/\S+ (\d+)#', $head, $m);
    return [(int) ($m[1] ?? 0), $head, $body];
}

test('landing-bg.php serves the image with its exact type, nosniff and a cache header', function () use ($name): void {
    [$st, $head, $body] = req('/landing-bg.php');
    same(200, $st, 'status');
    check(stripos($head, "content-type: image/png\r\n") !== false, 'content-type: ' . $head);
    check(stripos($head, 'x-content-type-options: nosniff') !== false, 'nosniff');
    check(preg_match('/cache-control: public, max-age=\d+/i', $head) === 1, 'cache-control');
    same(sample('png'), $body, 'bytes');
});
test('landing-bg.php takes no path input', function (): void {
    foreach (['?name=../secret.txt', '?file=/etc/passwd', '?v=1&image=../../secret.txt'] as $q) {
        [$st, , $body] = req('/landing-bg.php' . $q);
        check($st === 200 && !str_contains($body, 'SECRET') && $body === sample('png'), "$q gave $st");
    }
});
test('landing-bg.php says 404 when there is no image', function () use ($store): void {
    $c = $store->read('config');
    $c['landing'] = [];
    $store->write('config', $c);
    same(404, req('/landing-bg.php')[0], 'no image');
    $c['landing'] = ['image' => '../secret.txt'];
    $store->write('config', $c);
    same(404, req('/landing-bg.php')[0], 'bad name');
});
test('the router denies data/landing', function (): void {
    foreach (['/data/landing/', '/data/landing/background-0123456789abcdef.png', '/data/setup-code', '/Data/Landing/x.png', '/data%2Flanding%2Fx.png'] as $p) {
        $st = req($p)[0];
        check(in_array($st, [400, 403], true), "$p gave $st");
    }
});
test('the front page shows the sign-in button to a visitor', function (): void {
    [$st, , $body] = req('/');
    same(200, $st, 'status');
    check(str_contains($body, 'href="index.php?p=login"') && str_contains($body, 'landing-signin'), 'sign-in button');
    check(!str_contains($body, 'SECRET'), 'nothing private');
});
test('the Apache rules deny data/landing and the data folder keeps its .htaccess', function () use ($root): void {
    $conf = (string) file_get_contents($root . '/docker/apache-dps.conf');
    check(preg_match('/<LocationMatch "([^"]+)">\s*Require all denied/', $conf, $m) === 1, 'deny rule present');
    $re = '#' . str_replace('#', '\#', preg_replace('/^\(\?i\)/', '', $m[1])) . '#i';
    foreach (['/data/landing/background-0123456789abcdef.png', '/data/setup-code', '//data/landing/x.png', '/data./landing/x.png'] as $p) {
        check(preg_match($re, $p) === 1, "$p denied");
    }
    check(preg_match($re, '/landing-bg.php') !== 1, 'the endpoint is not denied');
    check(str_contains((string) file_get_contents($root . '/data/.htaccess'), 'Require all denied'), 'data/.htaccess');
});
finish();
