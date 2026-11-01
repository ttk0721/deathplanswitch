<?php
/**
 * Death Plan Switch: node-to-node API.
 *
 *   node-api.php?a=<route>
 *
 * A thin shell around DPS\NodeApi. Every request is signed by a node in the
 * cluster; there is no session and no cookie.
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'");
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex');

$respond = static function (int $status, array $json, ?string $body = null, array $headers = []): never {
    http_response_code($status);
    header('Content-Type: application/json');
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
    // $body is the exact text the response signature covers.
    exit($body ?? json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
};

try {
    if (!function_exists('DPS\\federation')) {
        $file = DPS_ROOT . '/app/Sync.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
    if (!function_exists('DPS\\federation')) {
        $respond(503, ['error' => 'federation not available']);
    }
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $body = '';
    if ($method === 'POST') {
        $max = 1048576;
        $h = fopen('php://input', 'rb');
        $body = $h === false ? '' : (string) stream_get_contents($h, $max + 1);
        if ($h !== false) {
            fclose($h);
        }
        if (strlen($body) > $max) {
            $respond(413, ['error' => 'too large']);
        }
    }
    // The signature covers the path and query exactly as the client sent them.
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $result = (new DPS\NodeApi(DPS\store(), DPS\federation()))->handle([
        'method' => $method,
        'a' => is_string($_GET['a'] ?? null) ? $_GET['a'] : '',
        'pathQuery' => $uri,
        'query' => $_GET,
        'body' => $body,
        'headers' => [
            'node' => (string) ($_SERVER['HTTP_X_DPS_NODE'] ?? ''),
            'time' => (string) ($_SERVER['HTTP_X_DPS_TIME'] ?? ''),
            'nonce' => (string) ($_SERVER['HTTP_X_DPS_NONCE'] ?? ''),
            'signature' => (string) ($_SERVER['HTTP_X_DPS_SIGNATURE'] ?? ''),
        ],
        'now' => DPS\Clock::now(),
    ]);
} catch (Throwable $e) {
    $respond(500, ['error' => 'server error']);
}

if (isset($result['file'])) {
    $size = @filesize($result['file']);
    if ($size === false) {
        $respond(404, ['error' => 'not found']);
    }
    http_response_code(200);
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $size);
    foreach ($result['headers'] ?? [] as $name => $value) {
        header($name . ': ' . $value);
    }
    readfile($result['file']);
    exit;
}
$respond($result['status'], $result['json'] ?? [], $result['body'] ?? null, $result['headers'] ?? []);
