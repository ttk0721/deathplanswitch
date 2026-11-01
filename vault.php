<?php
/**
 * Death Plan Switch: where a recipient opens the vault.
 *
 *   vault.php                 after release, with a code or password
 *   vault.php?invite=<token>  to set a password, using the code
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use DPS\Web;

Web::$layout = 'plain';
try {
    $origins = DPS\Cluster::publicOrigins(DPS\store());
} catch (Throwable $e) {
    $origins = [];
}
Web::headers($origins);
Web::page('vault_open');
