<?php
/**
 * Tiny test helpers for the suites in tests/. No framework, no Composer.
 *
 *   test('name', function () { same(1, 1, 'what'); });
 *   finish();
 */
declare(strict_types=1);

namespace DPS\Tests;

if (PHP_SAPI !== 'cli') {
    exit;
}
require_once dirname(__DIR__) . '/app/bootstrap.php';

/** Counters for the whole run, kept in one static array. */
function &counters(): array
{
    static $c = ['tests' => 0, 'checks' => 0, 'failed' => 0];
    return $c;
}

/** Runs one test. A failure is printed and counted, and the suite goes on. */
function test(string $name, callable $fn): void
{
    $c = &counters();
    $c['tests']++;
    try {
        $fn();
        echo "  ok    $name\n";
    } catch (\Throwable $e) {
        $c['failed']++;
        echo "  FAIL  $name: " . $e->getMessage() . "\n";
    }
}

/** Asserts a condition. The message is the failure text. */
function check(bool $condition, string $what): void
{
    $c = &counters();
    $c['checks']++;
    if (!$condition) {
        throw new \RuntimeException($what);
    }
}

/** Asserts two values are identical (===). */
function same(mixed $expected, mixed $actual, string $what): void
{
    check(
        $expected === $actual,
        $what . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
    );
}

/** Prints the totals and exits 1 if any test failed. */
function finish(): never
{
    $c = &counters();
    echo "\n{$c['tests']} tests, {$c['checks']} checks, {$c['failed']} failed\n";
    exit($c['failed'] > 0 ? 1 : 0);
}
