<?php
/**
 * Death Plan Switch: print the one-time setup code the installer asks for.
 *
 *   php cli/setup-code.php
 *
 * The code is made on first use and removed when the install finishes.
 * With --if-needed (used by the Docker entrypoint) it prints nothing once
 * the install is finished.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use DPS\SetupCode;

DPS\Cli::requireDataOwner();
$store = DPS\store();

if (in_array('--if-needed', $argv, true)) {
    SetupCode::announce($store);
    exit(0);
}
$code = SetupCode::ensure($store);
if ($code === null) {
    echo "This install is already set up. No setup code is needed.\n";
    exit(0);
}
echo SetupCode::announcement($code);
