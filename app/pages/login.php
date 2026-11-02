<?php
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

if (empty(store()->read('config')['installed_at'])) {
    Web::redirect('install.php');
}
if (Web::signedIn()) {
    Web::redirect('index.php');
}

$error = null;
if (Web::posted()) {
    $result = Web::tryPassword((string) ($_POST['password'] ?? ''));
    if ($result === true) {
        Web::signIn();
        Web::redirect('index.php');
    }
    $error = $result;
}

Web::$title = t('Sign in: Death Plan Switch');
?>
<h1><?= e(t('Sign in')) ?></h1>
<?php if ($error): ?>
<div class="notice notice-bad" role="alert"><p><?= e($error) ?></p></div>
<?php endif ?>
<form method="post" action="index.php?p=login">
  <?= Web::csrfField() ?>
  <div class="field">
    <label for="password"><?= e(t('Owner password')) ?></label>
    <input type="password" id="password" name="password" autocomplete="current-password" required autofocus>
  </div>
  <div class="actions">
    <button type="submit"><?= e(t('Sign in')) ?></button>
  </div>
</form>
<p class="small muted"><?= e(t('Forgotten it? Run {command} on the server.', ['command' => 'php cli/reset-password.php'])) ?></p>
