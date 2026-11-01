<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\Scheduler;
require __DIR__ . '/lib.php';

function pinger(array &$log): callable
{
    return function (string $url, string $body) use (&$log): bool { $log[] = [$url, $body]; return true; };
}
$fine = fn (): array => ['ok' => true, 'problems' => []];

test('the next run is the next quarter hour, strictly later', function (): void {
    same(1800, Scheduler::nextRunAt(1000), 'from 1000');
    same(2700, Scheduler::nextRunAt(1800), 'on a mark');
    same(120, Scheduler::nextRunAt(100, 60), 'interval 60');
});
test('the heartbeat goes to /fail when something is wrong', function (): void {
    same('https://hc-ping.com/abc', Scheduler::heartbeatTarget('https://hc-ping.com/abc', true), 'ok');
    same('https://hc-ping.com/abc/fail', Scheduler::heartbeatTarget('https://hc-ping.com/abc/', false), 'fail');
});
test('a tick stays fresh for 40 minutes', function (): void {
    check(Scheduler::tickIsFresh(1000, 3400), '2400 s is fresh');
    check(!Scheduler::tickIsFresh(1000, 3401), '2401 s is stale');
    check(!Scheduler::tickIsFresh(null, 5000), 'never ticked is stale');
});
test('a clean tick pings the heartbeat without its output', function () use ($fine): void {
    $log = [];
    $r = Scheduler::runOnce(fn (): array => [0, "Sent Kim Rivers their message.\n"], $fine, pinger($log), 'https://hc/x');
    same(['exit' => 0, 'ok' => true, 'pinged' => 'https://hc/x'], $r, 'result');
    same([['https://hc/x', 'ok']], $log, 'ping');
});
test('a failing tick with long output sends a capped body', function () use ($fine): void {
    $log = [];
    Scheduler::runOnce(fn (): array => [1, str_repeat('x', 5000)], $fine, pinger($log), 'https://hc/x');
    check(strlen($log[0][1]) <= 2003, 'at most 2003 bytes, got ' . strlen($log[0][1]));
    check(str_ends_with($log[0][1], '…'), 'marked as cut');
});
test('a failed tick pings /fail with its output', function () use ($fine): void {
    $log = [];
    $r = Scheduler::runOnce(fn (): array => [1, 'boom'], $fine, pinger($log), 'https://hc/x');
    same(['exit' => 1, 'ok' => false, 'pinged' => 'https://hc/x/fail'], $r, 'result');
    same('boom', $log[0][1], 'body');
});
test('health problems fail the heartbeat even after a clean tick', function (): void {
    $log = [];
    $r = Scheduler::runOnce(fn (): array => [0, 'ok'], fn (): array => ['ok' => false, 'problems' => ['mail', 'cron']], pinger($log), 'https://hc/x');
    same(false, $r['ok'], 'not ok');
    same("ok\nProblems: mail, cron", $log[0][1], 'body');
});
test('a tick or health check that throws still reports', function (): void {
    $log = [];
    $r = Scheduler::runOnce(function (): array { throw new \RuntimeException('crash'); },
        function (): array { throw new \RuntimeException('x'); }, pinger($log), 'https://hc/x');
    same(['exit' => 1, 'ok' => false, 'pinged' => 'https://hc/x/fail'], $r, 'result');
    same("crash\nProblems: health", $log[0][1], 'body');
});
test('no heartbeat address, no ping', function () use ($fine): void {
    $log = [];
    same(null, Scheduler::runOnce(fn (): array => [0, ''], $fine, pinger($log), '')['pinged'], 'pinged');
    same([], $log, 'nothing sent');
});
test('a slow tick interval widens the freshness window', function (): void {
    same(900, Scheduler::parseInterval(false), 'unset');
    same(900, Scheduler::parseInterval('abc'), 'junk');
    same(60, Scheduler::parseInterval('5'), 'clamped');
    same(3600, Scheduler::parseInterval('3600'), 'valid');
    same(2400, Scheduler::maxTickAge(900), 'default stays 40 minutes');
    same(7500, Scheduler::maxTickAge(3600), 'hourly ticks stay healthy');
});
finish();
