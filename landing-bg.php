<?php
/**
 * Death Plan Switch: the owner's own front-page background.
 * No input: it serves the one file the settings point to, with its exact type.
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use DPS\Landing;
use function DPS\store;

$store = store();
$landing = [];
try {
    $landing = (array) ($store->read('config')['landing'] ?? []);
} catch (Throwable) {
}
$name = $landing['image'] ?? null;
$path = Landing::imagePath($store, $name);
$mime = is_string($name) ? Landing::mime($name) : null;
if ($path === null || $mime === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo "Not found\n";
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cross-Origin-Resource-Policy: same-origin');
header('Cache-Control: public, max-age=86400');
readfile($path);
