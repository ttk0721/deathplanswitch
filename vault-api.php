<?php
/**
 * Death Plan Switch: vault API.
 *
 *   vault-api.php?a=<route>
 *
 * A thin shell around DPS\VaultApi: it builds the request, sends the answer.
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'");
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex');

$respond = static function (int $status, array $json): never {
    http_response_code($status);
    header('Content-Type: application/json');
    exit(json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
};

try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $body = '';
    if ($method === 'POST' || $method === 'PUT') {
        $max = 4194304 + 64;
        $h = fopen('php://input', 'rb');
        $body = $h === false ? '' : (string) stream_get_contents($h, $max + 1);
        if ($h !== false) {
            fclose($h);
        }
        if (strlen($body) > $max) {
            $respond(413, ['error' => 'too large']);
        }
    }
    $a = is_string($_GET['a'] ?? null) ? $_GET['a'] : '';
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    // Only a request that carries the session cookie may start a session:
    // anonymous recipients must not create a session file (or cookie) each.
    $owner = isset($_COOKIE['dps']) && DPS\Web::signedIn();
    $csrf = $owner && is_string($token) && hash_equals(DPS\Web::csrf(), $token);
    // Free the session lock: uploads and downloads can take a while.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $result = (new DPS\VaultApi(new DPS\Vault(DPS\store())))->handle([
        'method' => $method,
        'a' => $a,
        'query' => $_GET,
        'body' => $body,
        'owner' => $owner,
        'csrf' => $csrf,
        'released' => DPS\engine()->released(),
    ]);
} catch (Throwable $e) {
    $respond(500, ['error' => 'server error']);
}

if (isset($result['file'])) {
    $size = filesize($result['file']);
    if ($size === false) {
        $respond(404, ['error' => 'not found']);
    }
    http_response_code(200);
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $size);
    readfile($result['file']);
    exit;
}
$respond($result['status'], $result['json'] ?? []);
