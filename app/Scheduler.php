<?php
/**
 * Death Plan Switch: the rules behind the scheduler container.
 *
 * Pure functions, so the timing and the heartbeat can be tested without a
 * clock, a child process or a network. bin/scheduler.php does the real work.
 */
declare(strict_types=1);

namespace DPS;

final class Scheduler
{
    /** The next multiple of $interval after $now, so runs land on the quarter hour. */
    public static function nextRunAt(int $now, int $interval = 900): int
    {
        return intdiv($now, $interval) * $interval + $interval;
    }

    /** Seconds between runs from the DPS_TICK_INTERVAL text: bad values give 900, under 60 gives 60. */
    public static function parseInterval(string|false|null $raw): int
    {
        if (!is_string($raw) || !preg_match('/^\s*\d+\s*$/', $raw)) {
            return 900;
        }
        return max(60, (int) $raw);
    }

    /** How old a tick may be before the container is unhealthy: at least 40 minutes, and two intervals. */
    public static function maxTickAge(int $interval): int
    {
        return max(2400, 2 * $interval + 300);
    }

    /** Healthchecks.io convention: the same URL, plus /fail when something is wrong. */
    public static function heartbeatTarget(string $base, bool $ok): string
    {
        $base = rtrim($base, '/');
        return $ok ? $base : $base . '/fail';
    }

    /** Has the scheduler ticked recently enough? Never ticked counts as stale. */
    public static function tickIsFresh(?int $lastTick, int $now, int $maxAge = 2400): bool
    {
        return $lastTick !== null && $now - $lastTick <= $maxAge;
    }

    /**
     * One run: tick, check health, tell the outside world.
     *
     * Nothing escapes. A crash in either step must still reach the heartbeat as
     * a failure, because silence is what the heartbeat exists to catch.
     *
     * The heartbeat body goes to a third party, so it is kept private: on
     * success (exit 0) it is "ok", plus "\nProblems: a, b" when health reports
     * problems. The tick output (which may name contacts) is included only when
     * the exit code is not 0. The whole body is capped at 2000 bytes, then "…".
     *
     * @param callable():array{0:int,1:string} $tick
     * @param callable():array{ok:bool,problems:string[]} $health
     * @param callable(string,string):bool $ping
     * @return array{exit:int,ok:bool,pinged:?string}
     */
    public static function runOnce(callable $tick, callable $health, callable $ping, string $heartbeat): array
    {
        try {
            [$exit, $output] = $tick();
            $exit = (int) $exit;
            $output = (string) $output;
        } catch (\Throwable $e) {
            $exit = 1;
            $output = $e->getMessage();
        }
        try {
            $report = $health();
            $healthy = (bool) $report['ok'];
            $problems = array_map('strval', $report['problems'] ?? []);
        } catch (\Throwable) {
            $healthy = false;
            $problems = ['health'];
        }

        $ok = $exit === 0 && $healthy;
        // The tick output can name contacts, and the heartbeat goes to a third
        // party, so it is sent only when the tick failed.
        $body = $exit === 0 ? 'ok' : trim($output);
        if ($problems !== []) {
            $body .= "\nProblems: " . implode(', ', $problems);
        }
        if (strlen($body) > 2000) {
            $body = (function_exists('mb_strcut') ? mb_strcut($body, 0, 2000, 'UTF-8') : substr($body, 0, 2000)) . '…';
        }

        $pinged = null;
        if ($heartbeat !== '') {
            $pinged = self::heartbeatTarget($heartbeat, $ok);
            try {
                $ping($pinged, $body);
            } catch (\Throwable) {
                // A dead network must not stop the loop.
            }
        }
        return ['exit' => $exit, 'ok' => $ok, 'pinged' => $pinged];
    }
}
