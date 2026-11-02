<?php
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

/*
 * The public front page. Visitors learn one thing: whether the switch has
 * been triggered. The weekly check-in and its escalation are never shown, so
 * an overdue check-in looks exactly like a quiet week.
 */
$config = store()->read('config');
$installed = !empty($config['installed_at']);
if (!$installed) {
    // Nothing is saved yet: speak the visitor's language, Polish when unknown.
    I18n::setLanguage(I18n::fromAcceptLanguage((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) ?? 'pl');
}
$landing = (array) ($config['landing'] ?? []);
$title = trim((string) ($landing['title'] ?? '')) ?: 'Death Plan Switch';
$quote = trim((string) ($landing['quote'] ?? ''));
$by = trim((string) ($landing['by'] ?? '')) ?: trim((string) ($config['owner_name'] ?? ''));
$custom = trim((string) ($landing['text'] ?? ''));
$paragraphs = $custom !== '' ? (preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $custom)) ?: []) : [];
$background = Landing::choice(store(), $landing);

$state = 'setup';
if ($installed) {
    $released = engine()->released();
    $armed = !$released && (bool) (engine()->state()['armed'] ?? false);
    // While armed and not fired every stage looks the same: the weekly check-in,
    // reminders and verification never change a byte of the page.
    $state = $released ? 'fired' : ($armed ? 'armed' : 'off');
}
$hasRecipients = $state === 'fired' && (new Vault(store()))->read()['recipients'] !== [];

Web::$layout = 'landing';
Web::$title = $title;
?>
<div class="landing">
  <img class="landing-bg" src="<?= e(Landing::src($background, $landing)) ?>" alt="">
  <div class="landing-scrim"></div>
  <header class="landing-top">
<?php if ($quote !== ''): ?>
    <h1 class="landing-name"><?= e($title) ?></h1>
<?php endif ?>
    <a class="button landing-signin" href="index.php?p=login"><?= e(t('Sign in')) ?></a>
  </header>
  <div class="landing-body">
<?php if ($quote !== ''): ?>
    <figure class="landing-figure">
      <blockquote class="landing-quote"><p><?= e($quote) ?></p></blockquote>
<?php if ($by !== ''): ?>
      <figcaption class="landing-by">— <?= e($by) ?></figcaption>
<?php endif ?>
    </figure>
<?php else: ?>
    <p class="landing-quote landing-quote-plain" role="heading" aria-level="1"><?= e($title) ?></p>
<?php endif ?>
    <section class="pilot landing-status pilot-<?= $state === 'fired' ? 'fired' : ($state === 'setup' ? 'off' : ($state === 'off' ? 'off' : 'armed')) ?>" aria-live="polite">
      <span class="pilot-dot" aria-hidden="true"></span>
<?php if ($state === 'fired'): ?>
      <p class="landing-state"><?= e(t('This switch has been triggered.')) ?></p>
<?php elseif ($state === 'setup'): ?>
      <p class="landing-state"><?= e(t('This switch is being set up.')) ?></p>
<?php elseif ($state === 'off'): ?>
      <p class="landing-state"><?= e(t('This switch is not active.')) ?> <?= e(t('All is well.')) ?></p>
<?php elseif ($state === 'armed'): ?>
      <p class="landing-state"><?= e(t('This switch is armed.')) ?> <?= e(t('All is well.')) ?></p>
<?php endif ?>
    </section>
<?php if ($hasRecipients): ?>
    <p class="landing-action"><a class="button" href="vault.php"><?= e(t('Open the vault')) ?></a></p>
<?php elseif ($state === 'setup'): ?>
    <p class="landing-action"><a class="button" href="install.php"><?= e(t('Set up')) ?></a></p>
<?php endif ?>
<?php if ($paragraphs !== []): ?>
    <div class="landing-text">
<?php foreach ($paragraphs as $paragraph): ?>
      <p><?= e(trim($paragraph)) ?></p>
<?php endforeach ?>
    </div>
<?php endif ?>
  </div>
</div>
