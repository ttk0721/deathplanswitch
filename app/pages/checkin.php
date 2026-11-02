<?php
/**
 * The check-in page the weekly email links to.
 *
 * Opening this page does nothing. Email scanners follow links, so only
 * pressing the button, which sends a POST, counts as a check-in.
 */
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

$engine = engine();
$token = Web::isPost() ? Web::post('t') : Web::get('t');
$config = $engine->config();
$tz = (string) ($config['timezone'] ?? 'UTC');
$result = null;
$live = false;

if (Web::isPost()) {
    $result = $engine->checkInByToken($token);
} else {
    $live = $engine->checkinTokenIsLive($token);
}

Web::$title = t('Check in: Death Plan Switch');
?>
<?php if ($result !== null): ?>
<h1><?= e(t('Checked in')) ?></h1>
<?php if ($result['stage'] === 'fired'): ?>
<p><?= e($result['told']
    ? t('Your switch had fired. Everyone who was contacted has now been told it was a false alarm: {names}.', ['names' => implode(', ', $result['told'])])
    : t('Your switch had fired. Everyone who was contacted has now been told it was a false alarm.')) ?></p>
<p><?= t('Your contacts have read their messages, so any pointer in them has been seen. {link} to review what needs changing.', ['link' => '<a href="index.php">' . e(t('Sign in')) . '</a>']) ?></p>
<?php elseif ($result['told']): ?>
<p><?= e(count($result['told']) === 1
    ? t('{names} had been asked whether you are all right, and has now been told it was a false alarm. Nobody else was contacted.', ['names' => implode(', ', $result['told'])])
    : t('{names} had been asked whether you are all right, and have now been told it was a false alarm. Nobody else was contacted.', ['names' => implode(', ', $result['told'])])) ?></p>
<?php else: ?>
<p><?= e(t('Thank you. Nobody was contacted.')) ?></p>
<?php endif ?>
<?php if ($result['paused_until']): ?>
<p><?= e(t('Your switch is paused until {date}.', ['date' => when($result['paused_until'], $tz, 'day')])) ?></p>
<?php elseif ($result['next_due_at']): ?>
<p><?= e(t('Your next check-in email arrives on {date} at {time}.', ['date' => when($result['next_due_at'], $tz, 'day'), 'time' => when($result['next_due_at'], $tz, 'time')])) ?></p>
<?php endif ?>

<?php elseif ($live): ?>
<h1><?= e(t('Are you there, {name}?', ['name' => strtok((string) ($config['owner_name'] ?? ''), ' ') ?: t('owner')])) ?></h1>
<p><?= e(t('Press the button to check in. Opening this page is not enough.')) ?></p>
<form method="post" action="checkin.php">
  <input type="hidden" name="t" value="<?= e($token) ?>">
  <div class="actions"><button class="button-big" type="submit"><?= e(t('I am here: check in')) ?></button></div>
</form>

<?php else: ?>
<h1><?= e(t('This link is no longer active')) ?></h1>
<p><?= e(t('Each check-in link works once, and for 14 days. If you have a newer email from Death Plan Switch, use the link in that one.')) ?></p>
<p><?= t('You can also {link} and check in from the dashboard.', ['link' => '<a href="index.php">' . e(t('sign in')) . '</a>']) ?></p>
<?php endif ?>
