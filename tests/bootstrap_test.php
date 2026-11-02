<?php
declare(strict_types=1);
namespace DPS\Tests;
require __DIR__ . '/lib.php';

function phpRun(string $env, string $code): string
{
    return (string) shell_exec('env -u DPS_DATA -u DPS_DOCKER ' . $env . ' ' . escapeshellarg(PHP_BINARY)
        . ' -r ' . escapeshellarg('require "' . dirname(__DIR__) . '/app/bootstrap.php"; ' . $code));
}

test('DPS_DATA comes from the environment, without a trailing slash', function (): void {
    same('/tmp/dps-env/', phpRun('DPS_DATA=/tmp/dps-env/', 'echo DPS_DATA, "/";'), 'data folder');
    same('/tmp/dps-env', phpRun('DPS_DATA=/tmp/dps-env/', 'echo DPS_DATA;'), 'trimmed');
});
test('without the variable the data folder sits beside the app', function (): void {
    same(dirname(__DIR__) . '/data', phpRun('', 'echo DPS_DATA;'), 'default');
});
test('inDocker follows DPS_DOCKER=1 only', function (): void {
    same('yes', phpRun('DPS_DOCKER=1', 'echo DPS\inDocker() ? "yes" : "no";'), 'set');
    same('no', phpRun('DPS_DOCKER=0', 'echo DPS\inDocker() ? "yes" : "no";'), 'zero');
    same('no', phpRun('', 'echo DPS\inDocker() ? "yes" : "no";'), 'unset');
});
finish();
