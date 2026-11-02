<?php
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

$config = store()->read('config');
$landing = (array) ($config['landing'] ?? []);
$title = trim((string) ($landing['title'] ?? '')) ?: 'Death Plan Switch';
$custom = trim((string) ($landing['text'] ?? ''));
$released = engine()->released();
$armed = !$released && (bool) (engine()->state()['armed'] ?? false);
$hasRecipients = $released && (new Vault(store()))->read()['recipients'] !== [];
$paragraphs = $custom !== '' ? (preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $custom)) ?: []) : [];

Web::$layout = 'plain';
Web::$title = $title;
?>
<h1 class="public-title"><?= e($title) ?></h1>
<?php if ($released): ?>
<section class="pilot pilot-fired" aria-live="polite">
  <span class="pilot-dot" aria-hidden="true"></span>
  <p class="pilot-state"><?= e(t('Triggered')) ?></p>
  <p class="pilot-line"><?= e(t('This switch has been triggered.')) ?></p>
</section>
<?php if ($hasRecipients): ?>
<p><a class="button" href="vault.php"><?= e(t('Open the vault')) ?></a></p>
<?php endif ?>
<?php else: ?>
<section class="pilot pilot-<?= $armed ? 'armed' : 'off' ?>">
  <span class="pilot-dot" aria-hidden="true"></span>
  <p class="pilot-state"><?= e($armed ? t('Armed') : t('Not active')) ?></p>
  <p class="pilot-line"><?= e($armed ? t('This switch is armed.') : t('This switch is not active.')) ?></p>
</section>
<?php endif ?>
<?php foreach ($paragraphs as $paragraph): ?>
  <p><?= e(trim($paragraph)) ?></p>
<?php endforeach ?>
<p class="help"><a href="index.php?p=login"><?= e(t('Owner sign-in')) ?></a></p>
