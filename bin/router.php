<?php
/**
 * Death Plan Switch: router for PHP's built-in web server.
 *
 *   php -S 127.0.0.1:8080 -t <app> <app>/bin/router.php
 *
 * Native installs have no Apache, so this file enforces the rules that
 * docker/apache-dps.conf enforces. It works from an allow-list: only the
 * entry points and the files under assets/ are ever served, everything else
 * is 403 (private) or 404. The path is decoded exactly once, then normalised;
 * a file is handed to the server only when the request path is already in
 * that normal form, so the server and this router cannot disagree.
 *
 * Status codes: 400 malformed path, 403 private path, 404 anything else.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

/** Ends the request with a plain-text answer. */
function dps_router_stop(int $code, string $text): never
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo $text;
    exit;
}

/**
 * Decodes once and normalises. Returns the lower-case segments, or null when
 * the path is malformed.
 *
 * @return array{0: list<string>, 1: string}|null segments (lower-case), normal form (original case)
 */
function dps_router_normalise(string $target): ?array
{
    if ($target === '' || $target[0] !== '/') {
        return null;
    }
    $cut = strcspn($target, '?#');
    $path = rawurldecode(substr($target, 0, $cut));
    // Control characters, DEL, ":" (Windows streams, drive letters) and the Windows wildcard characters.
    if (preg_match('/[\x00-\x1f\x7f:<>"|*?]/', $path) === 1) {
        return null;
    }
    $path = str_replace('\\', '/', $path);

    $segments = [];
    $original = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        // Windows ignores trailing dots and spaces, so "app." and "app " are "app".
        $trimmed = rtrim($segment, '. ');
        if ($trimmed === '') {
            return null; // "..", "...", " " and the like
        }
        $segments[] = strtolower($trimmed);
        $original[] = $segment;
    }
    return [$segments, '/' . implode('/', $original)];
}

$target = (string) ($_SERVER['REQUEST_URI'] ?? '');
$normal = dps_router_normalise($target);
if ($normal === null) {
    dps_router_stop(400, "Bad request\n");
}
[$segments, $canonical] = $normal;

// Private paths: 403, whether or not the file exists.
$privateFolders = ['data', 'app', 'lang', 'cli', 'tests', 'docs', 'bin', 'docker', 'packaging'];
$privateTypes = ['md', 'yml', 'yaml', 'json', 'bak', 'log', 'example', 'sh', 'ps1'];
$deny = $segments !== [] && in_array($segments[0], $privateFolders, true);
foreach ($segments as $segment) {
    if ($segment[0] === '.') {
        $deny = true; // dotfiles and dot folders
    }
}
if ($segments !== []) {
    $name = end($segments);
    $type = pathinfo($name, PATHINFO_EXTENSION);
    if (
        in_array($type, $privateTypes, true)
        || $name === 'version'
        || preg_match('/^(dockerfile|docker-compose|compose\.|config\.local)/', $name) === 1
    ) {
        $deny = true;
    }
}
if ($deny) {
    dps_router_stop(403, "Forbidden\n");
}

$docroot = (string) realpath(__DIR__ . '/..');
$entryPoints = [
    'index.php', 'install.php', 'checkin.php', 'confirm.php', 'respond.php',
    'vault.php', 'vault-api.php', 'node-api.php', 'health.php', 'tick.php',
];

// Anything but the exact normal form (encoded characters, "//", trailing
// slash or dots, path info, other case) is never handed to the server.
$exact = strtok($target, '?#') === $canonical;

if ($segments === [] ) {
    if (strtok($target, '?#') !== '/') {
        dps_router_stop(404, "Not found\n");
    }
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $docroot . '/index.php';
    return false;
}

if ($exact && count($segments) === 1 && in_array(substr($canonical, 1), $entryPoints, true)) {
    return false;
}

if ($exact && count($segments) >= 2 && $segments[0] === 'assets') {
    $file = realpath($docroot . $canonical);
    $assets = realpath($docroot . '/assets');
    $dataDir = getenv('DPS_DATA');
    $data = $dataDir !== false && $dataDir !== '' ? realpath($dataDir) : false;
    if (
        $file !== false && $assets !== false
        && !($data !== false && ($file === $data || strncmp($file, $data . DIRECTORY_SEPARATOR, strlen($data) + 1) === 0))
        && !in_array(strtolower(pathinfo($canonical, PATHINFO_EXTENSION)), ['php', 'phtml'], true)
        && is_file($file)
        && strncmp($file, $assets . DIRECTORY_SEPARATOR, strlen($assets) + 1) === 0
        && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php'
        && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'phtml'
    ) {
        return false;
    }
}

dps_router_stop(404, "Not found\n");
