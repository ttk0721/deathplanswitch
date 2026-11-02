<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Cluster, Store, Web};
require __DIR__ . '/lib.php';

function corsDir(): string {
    $dir = sys_get_temp_dir() . '/dps-cors-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700);
    $pk = rtrim(strtr(base64_encode(str_repeat('a', 32)), '+/', '-_'), '=');
    $node = static fn(string $h, int $r, string $pub): array => ['id' => 'n_' . $h, 'name' => 'N' . $r, 'url' => 'https://x' . $r . '.example.org',
        'sign_pk' => $pk, 'share_pk' => 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE', 'rank' => $r, 'public_url' => $pub];
    Cluster::save(new Store($dir), ['k' => 1, 'primary' => null, 'isolation_hours' => 48, 'step_delay_min' => 30,
        'nodes' => [$node('0a1b2c3d', 0, 'https://n1.deadman.example'), $node('0a1b2c3e', 1, 'https://n2.deadman.example:8443')]]);
    return $dir;
}

/** Starts php -S on a free port with the given data dir; returns [process, pipes, port]. */
function serve(string $dir): array {
    $sock = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int)substr(strrchr((string)stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);
    $proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', dirname(__DIR__)], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes, null, ['DPS_DATA' => $dir, 'PATH' => getenv('PATH') ?: '']);
    for ($i = 0; $i < 50; $i++) {
        $c = @fsockopen('127.0.0.1', $port, $en, $es, 0.2);
        if ($c) { fclose($c); break; }
        usleep(100000);
    }
    return [$proc, $port];
}

/** One request to vault-api.php; returns [response header lines, status code]. */
function hit(string $dir, string $method, string $a, ?string $origin): array {
    if (!isset($GLOBALS['corsSrv'][$dir])) {
        $GLOBALS['corsSrv'][$dir] = serve($dir);
    }
    [, $port] = $GLOBALS['corsSrv'][$dir];
    $ctx = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'timeout' => 10,
        'header' => $origin === null ? '' : 'Origin: ' . $origin . "\r\n"]]);
    @file_get_contents('http://127.0.0.1:' . $port . '/vault-api.php?a=' . $a, false, $ctx);
    $lines = $http_response_header ?? [];
    preg_match('/ (\d{3})/', $lines[0] ?? '', $m);
    return [array_slice($lines, 1), (int)($m[1] ?? 0)];
}
register_shutdown_function(static function (): void {
    foreach ($GLOBALS['corsSrv'] ?? [] as [$proc]) {
        proc_terminate($proc);
        proc_close($proc);
    }
});
function has(array $headers, string $line): bool {
    return in_array($line, $headers, true);
}
function anyStarting(array $headers, string $prefix): bool {
    foreach ($headers as $h) {
        if (stripos($h, $prefix) === 0) { return true; }
    }
    return false;
}

test('csp lets the page load its own fonts and nothing else', function (): void {
    check(str_contains(Web::csp(), "font-src 'self';"), 'fonts from the same origin');
    check(!str_contains(Web::csp(), 'font-src *'), 'not from anywhere');
});
test('csp adds validated origins to connect-src', function (): void {
    check(str_contains(Web::csp(['https://a.example']), "connect-src 'self' https://a.example;"), 'added');
    check(str_contains(Web::csp(), "connect-src 'self';"), 'default unchanged');
    check(!str_contains(Web::csp(['https://a.example; script-src *', '*', 'ftp://x.example']), 'script-src *'), 'bad origins dropped');
    same(Web::csp(), Web::csp(['https://a.example; script-src *', 'ftp://x.example']), 'only bad origins equals default');
});
test('cluster public origin gets ACAO and Vary', function (): void {
    $dir = corsDir();
    foreach (['status', 'public', 'release', 'chunk'] as $a) {
        [$h] = hit($dir, 'GET', $a, 'https://n2.deadman.example:8443');
        check(has($h, 'Access-Control-Allow-Origin: https://n2.deadman.example:8443'), "$a acao");
        check(has($h, 'Vary: Origin'), "$a vary");
        check(!anyStarting($h, 'Access-Control-Allow-Credentials'), "$a no credentials");
    }
});
test('other origins and other routes get no CORS', function (): void {
    $dir = corsDir();
    [$h] = hit($dir, 'GET', 'status', 'https://evil.example');
    check(!anyStarting($h, 'Access-Control-Allow-Origin'), 'foreign origin');
    [$h] = hit($dir, 'GET', 'status', 'https://n1.deadman.example.evil.example');
    check(!anyStarting($h, 'Access-Control-Allow-Origin'), 'suffix origin');
    [$h] = hit($dir, 'GET', 'owner', 'https://n1.deadman.example');
    check(!anyStarting($h, 'Access-Control-Allow-Origin'), 'owner route');
    [$h] = hit($dir, 'GET', 'status', null);
    check(!anyStarting($h, 'Access-Control-Allow-Origin'), 'no origin');
});
test('preflight answers 204, ACAO only for cluster origins', function (): void {
    $dir = corsDir();
    [$h, $code] = hit($dir, 'OPTIONS', 'release', 'https://n1.deadman.example');
    same(204, $code, 'code');
    check(has($h, 'Access-Control-Allow-Origin: https://n1.deadman.example'), 'acao');
    check(has($h, 'Access-Control-Allow-Methods: GET'), 'methods');
    [$h, $code] = hit($dir, 'OPTIONS', 'release', 'https://evil.example');
    same(204, $code, 'code foreign');
    check(!anyStarting($h, 'Access-Control-Allow'), 'foreign none');
});
test('corrupt cluster.json still answers status, without CORS', function (): void {
    $dir = corsDir();
    file_put_contents($dir . '/cluster.json', '{not json');
    [$h, $code] = hit($dir, 'GET', 'status', 'https://n1.deadman.example');
    same(200, $code, 'status code');
    check(!anyStarting($h, 'Access-Control-Allow-Origin'), 'no acao');
});
finish();
