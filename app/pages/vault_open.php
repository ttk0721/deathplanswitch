<?php
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

Web::$layout = 'plain';
Web::$title = 'Vault: Death Plan Switch';
?>
<h1>Vault</h1>
<div id="vault-recipient" class="vault" data-api="vault-api.php" data-invite="<?= e(Web::get('invite')) ?>"></div>
<noscript><p class="lede">This page needs JavaScript, because everything is decrypted in your browser.</p></noscript>
<script type="module" src="assets/vault/recipient.js?v=<?= e(VERSION) ?>"></script>
