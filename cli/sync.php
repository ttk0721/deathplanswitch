<?php
/**
 * Death Plan Switch: talk to the other nodes once.
 *
 *   php cli/sync.php
 *
 * Started by bin/scheduler.php before each cron run when this node is part of
 * a cluster. Does nothing on a single node.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';
require_once DPS_ROOT . '/app/Sync.php';

DPS\Cli::requireDataOwner();

try {
    $store = DPS\store();
    $identity = DPS\Node::publicIdentity($store);
    if ($identity === null || !DPS\Cluster::enabled($store)) {
        echo "Not part of a cluster.\n";
        exit(0);
    }
    $sync = new DPS\Sync(
        $store,
        DPS\federatedEngine(),
        new DPS\PeerClient($store),
        new DPS\Coordinator($store, $identity['id']),
        $identity['id']
    );
    foreach ($sync->run(240) as $line) {
        echo $line . "\n";
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Death Plan Switch could not sync: ' . $e->getMessage() . "\n");
    exit(1);
}
