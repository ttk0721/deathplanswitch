<?php
/**
 * Smoke test helper: stands in for Healthchecks.io.
 * Logs "METHOD URI" for every request and answers OK.
 */
declare(strict_types=1);

file_put_contents('/out/requests.log', $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);
echo 'OK';
