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
const SYNC_TIMEOUT = 280;

/** Seconds between runs: bad values fall back to 900, anything under 60 becomes 60. */
function tickInterval(): int
{
    return Scheduler::parseInterval(getenv('DPS_TICK_INTERVAL'));
}

/** Run a PHP script in a child process; kill it after $timeout seconds. @return array{0:int,1:string} */
function runChild(string $script, array $args, int $timeout): array
{
    $name = basename($script);
    $process = @proc_open(
        array_merge([PHP_BINARY, $script], $args),
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        return [1, "Could not start {$name}."];
    }
    foreach ($pipes as $pipe) {
        stream_set_blocking($pipe, false);
    }
    $output = '';
    $deadline = time() + $timeout;
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
        return [1, $output . "{$name} was killed after {$timeout} seconds.\n"];
    }
    return [$code, $output];
}

/** Whether this node is in a cluster of two or more. Never throws. */
function inCluster(): bool
{
    try {
        return DPS\Cluster::enabled(new Store(DPS_DATA));
    } catch (Throwable) {
        return false;
    }
}

/** Run cron.php, after one sync with the other nodes when in a cluster. @return array{0:int,1:string} */
function runTick(): array
{
    if (inCluster()) {
        try {
            [$code, $out] = runChild(DPS_ROOT . '/cli/sync.php', [], SYNC_TIMEOUT);
            $lines = array_values(array_filter(explode("\n", trim($out))));
            echo gmdate('Y-m-d H:i:s') . ' sync exit=' . $code . ($lines ? ' ' . $lines[array_key_last($lines)] : '') . "\n";
        } catch (Throwable $e) {
            echo gmdate('Y-m-d H:i:s') . ' sync failed (' . $e->getMessage() . ")\n";
        }
    }
    return runChild(DPS_ROOT . '/cron.php', ['--verbose'], TICK_TIMEOUT);
}

/** Health of the switch; a fresh install has nothing to report yet. */
function checkHealth(): array
{
    $store = new Store(DPS_DATA);
    if (empty($store->read('config')['installed_at'])) {
        return ['ok' => true, 'problems' => []];
    }
    // On a federated node the engine carries a Coordinator, so health() reports a cut-off node itself.
    $coordinator = null;
    try {
        $id = DPS\Node::publicIdentity($store)['id'] ?? null;
        $coordinator = $id !== null ? new DPS\Coordinator($store, $id) : null;
    } catch (Throwable) {
    }
    $health = (new Engine($store, new Outbox($store, new SmtpTransport()), null, $coordinator))->health();
    return $health;
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

// Native installs have no container log, so the service log carries the setup code.
DPS\SetupCode::announce(new Store(DPS_DATA));

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
