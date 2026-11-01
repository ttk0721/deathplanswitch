<?php
/**
 * Death Plan Switch: the scheduler container's main loop.
 *
 * Runs cron.php in a child process every DPS_TICK_INTERVAL seconds (default
 * 900) and pings HEARTBEAT_URL after each run. A child process means a hung or
 * crashing run cannot take the loop down with it.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

use DPS\Engine;
use DPS\Outbox;
use DPS\Scheduler;
use DPS\SmtpTransport;
use DPS\Store;

const TICK_TIMEOUT = 300;

/** Seconds between runs: bad values fall back to 900, anything under 60 becomes 60. */
function tickInterval(): int
{
    return Scheduler::parseInterval(getenv('DPS_TICK_INTERVAL'));
}

/** Run cron.php; kill it after TICK_TIMEOUT seconds. @return array{0:int,1:string} */
function runTick(): array
{
    $process = @proc_open(
        [PHP_BINARY, DPS_ROOT . '/cron.php', '--verbose'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        return [1, 'Could not start cron.php.'];
    }
    foreach ($pipes as $pipe) {
        stream_set_blocking($pipe, false);
    }
    $output = '';
    $deadline = time() + TICK_TIMEOUT;
    $timedOut = false;
    $code = -1;
    while (true) {
        foreach ($pipes as $pipe) {
            $output .= (string) stream_get_contents($pipe);
        }
        $status = proc_get_status($process);
        if (!$status['running']) {
            // proc_close would report -1 once the status call has reaped the child.
            $code = $status['exitcode'];
            break;
        }
        if (time() >= $deadline) {
            $timedOut = true;
            proc_terminate($process, 9);
            break;
        }
        usleep(200000);
    }
    foreach ($pipes as $pipe) {
        $output .= (string) stream_get_contents($pipe);
        fclose($pipe);
    }
    proc_close($process);
    if ($timedOut) {
        return [1, $output . 'cron.php was killed after ' . TICK_TIMEOUT . " seconds.\n"];
    }
    return [$code, $output];
}

/** Health of the switch; a fresh install has nothing to report yet. */
function checkHealth(): array
{
    $store = new Store(DPS_DATA);
    if (empty($store->read('config')['installed_at'])) {
        return ['ok' => true, 'problems' => []];
    }
    return (new Engine($store, new Outbox($store, new SmtpTransport())))->health();
}

/** POST the text to the heartbeat URL. True on 2xx; never throws. */
function ping(string $url, string $body): bool
{
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return $status >= 200 && $status < 300;
    } catch (Throwable) {
        return false;
    }
}

$interval = tickInterval();
$heartbeat = trim((string) getenv('HEARTBEAT_URL'));

while (true) {
    try {
        $result = Scheduler::runOnce(
            'runTick',
            'checkHealth',
            static fn (string $url, string $body): bool => ping($url, $body),
            $heartbeat
        );
        echo gmdate('Y-m-d H:i:s') . ' exit=' . $result['exit'] . ' ok=' . ($result['ok'] ? 'yes' : 'no') . "\n";
    } catch (Throwable $e) {
        echo gmdate('Y-m-d H:i:s') . ' exit=1 ok=no (' . $e->getMessage() . ")\n";
    }
    $until = Scheduler::nextRunAt(time(), $interval);
    while (($left = $until - time()) > 0) {
        sleep(min(60, $left));
    }
}
