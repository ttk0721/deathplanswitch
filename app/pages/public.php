<?php
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

$config = store()->read('config');
$landing = (array) ($config['landing'] ?? []);
$title = trim((string) ($landing['title'] ?? '')) ?: 'Death Plan Switch';
$text = trim((string) ($landing['text'] ?? '')) ?: 'This switch is not active.';
$released = engine()->released();
$hasRecipients = $released && (new Vault(store()))->read()['recipients'] !== [];

Web::$layout = 'plain';
Web::$title = $title;
?>
<div class="public-status">
<?php if ($released): ?>
  <h1>This switch has been triggered.</h1>
<?php if ($hasRecipients): ?>
  <p><a class="button" href="vault.php">Open the vault</a></p>
<?php endif ?>
<?php else: ?>
  <h1><?= e($title) ?></h1>
<?php foreach (preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $text)) as $paragraph): ?>
  <p><?= e(trim($paragraph)) ?></p>
<?php endforeach ?>
<?php endif ?>
</div>
<p class="help"><a href="index.php?p=login">Owner sign-in</a></p>
