<?php
declare(strict_types=1);
namespace DPS\Tests;
require __DIR__ . '/lib.php';

/**
 * Loads bootstrap in a child php with DPS_ROOT set to a temp root that holds
 * app/ (a symlink to the real app) and, when $version is not null, a VERSION file.
 * Returns the DPS\VERSION the child reports.
 */
function versionFor(?string $version): string
{
    $root = sys_get_temp_dir() . '/dps-version-' . bin2hex(random_bytes(6));
    mkdir($root, 0700, true);
    symlink(dirname(__DIR__) . '/app', $root . '/app');
    if ($version !== null) {
        file_put_contents($root . '/VERSION', $version);
    }
    $code = 'define("DPS_ROOT", "' . $root . '"); require DPS_ROOT . "/app/bootstrap.php"; echo DPS\VERSION;';
    $out = (string) shell_exec('env -u DPS_DATA -u DPS_DOCKER ' . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code));
    unlink($root . '/app');
    if ($version !== null) {
        unlink($root . '/VERSION');
    }
    rmdir($root);
    return $out;
}

test('DPS\VERSION comes from the VERSION file under DPS_ROOT, trimmed', function (): void {
    same('2.3.4', versionFor("2.3.4\n"), 'version from file');
});
test('a VERSION file that is not X.Y.Z falls back to 1.0.0', function (): void {
    same('1.0.0', versionFor('garbage'), 'garbage');
});
test('without a VERSION file DPS\VERSION is 1.0.0', function (): void {
    same('1.0.0', versionFor(null), 'missing file');
});
finish();
