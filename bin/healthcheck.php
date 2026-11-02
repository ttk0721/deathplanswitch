<?php
/**
 * Death Plan Switch: Docker healthcheck.
 *
 *   php bin/healthcheck.php web        is Apache answering?
 *   php bin/healthcheck.php scheduler  did the scheduler tick recently?
 *
 * Exit 0 when healthy, 1 when not, 2 for a bad argument.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

use DPS\Scheduler;
use DPS\Store;

$what = $argv[1] ?? '';

if ($what === 'scheduler') {
    try {
        $store = new Store(DPS_DATA);
        // Nothing ticks before the first setup, so that is not a failure.
        if (!$store->exists('state') && empty($store->read('config')['installed_at'])) {
            exit(0);
        }
        $last = $store->read('state')['last_tick_at'] ?? null;
        exit(Scheduler::tickIsFresh($last === null ? null : (int) $last, time(), Scheduler::maxTickAge(Scheduler::parseInterval(getenv('DPS_TICK_INTERVAL')))) ? 0 : 1);
    } catch (Throwable) {
        exit(1);
    }
}

if ($what === 'web') {
    $ch = curl_init('http://127.0.0.1/install.php');
    curl_setopt_array($ch, [CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_RETURNTRANSFER => true]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    exit($status >= 200 && $status <= 499 ? 0 : 1);
}

fwrite(STDERR, "Usage: php bin/healthcheck.php web|scheduler\n");
exit(2);
