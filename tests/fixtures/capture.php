<?php
declare(strict_types=1);
/** Router for `php -S`: records each request in $CAPTURE_DIR and answers 200, or 500 for /fail. */

$dir = (string) getenv('CAPTURE_DIR');
if ($dir !== '') {
    file_put_contents(
        $dir . '/' . sprintf('%.6f', microtime(true)) . '-' . bin2hex(random_bytes(2)) . '.json',
        json_encode([
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'headers' => getallheaders(),
            'body' => file_get_contents('php://input'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}
if (str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/fail')) {
    http_response_code(500);
    echo 'Failed';
    return true;
}
http_response_code(200);
echo 'OK';
return true;
