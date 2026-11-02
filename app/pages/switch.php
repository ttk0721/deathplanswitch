<?php
/**
 * The dashboard: the state of the switch, and the few things the owner
 * does to it (check in, arm, pause, disarm).
 */
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

Web::requireOwner();
$engine = engine();

if (Web::posted()) {
    switch (Web::post('action')) {
        case 'checkin':
            $result = $engine->checkIn('the dashboard');
            if (!$result['told']) {
                Web::flash(t('Checked in.'));
            } elseif (count($result['told']) === 1) {
                Web::flash(t('Checked in. {names} has been told it was a false alarm.', ['names' => implode(', ', $result['told'])]));
            } else {
                Web::flash(t('Checked in. {names} have been told it was a false alarm.', ['names' => implode(', ', $result['told'])]));
            }
            break;

        case 'arm':
            $errors = $engine->arm();
            if ($errors) {
                Web::flash(t('The switch cannot be armed yet. Still to do: {list}.', ['list' => implode('; ', $errors)]), 'bad');
            } else {
                Web::flash(t('The switch is armed. A confirmation email is on its way to you.'));
            }
            break;

        case 'pause':
            $result = $engine->pause((int) Web::post('days', '7'));
            if ($result === null) {
                Web::flash(t('Only an armed switch can be paused.'), 'bad');
            } else {
                Web::flash(t('Paused. The switch starts again by itself on {date}.', ['date' => when($result['until'], (string) $engine->config()['timezone'])]));
            }
            break;

        case 'resume':
            $engine->resume();
            Web::flash(t('The pause has ended. The switch is active again.'));
            break;

        case 'disarm':
            $check = Web::tryPassword((string) ($_POST['password'] ?? ''));
            if ($check !== true) {
                Web::flash(t('The switch was not disarmed. {reason}', ['reason' => (string) $check]), 'bad');
            } else {
                $told = $engine->disarm();
                $message = t('The switch is disarmed. Nobody will be contacted until you arm it again.');
                if ($told) {
                    $message .= ' ' . (count($told) === 1
                        ? t('{names} has been told it was a false alarm.', ['names' => implode(', ', $told)])
                        : t('{names} have been told it was a false alarm.', ['names' => implode(', ', $told)]));
                }
                Web::flash($message, 'warn');
            }
            break;

        case 'dismiss_after_fire':
            $engine->dismissAfterFireNotice();
            break;
    }
    Web::redirect('index.php');
}

$config = $engine->config();
$tz = (string) ($config['timezone'] ?? 'UTC');
$status = $engine->status();
$state = $status['state'];
$mode = $status['mode'];
$plan = $status['plan'];
$cycle = $state['cycle'];
$contacts = $engine->contacts()->all();
$nok = $engine->contacts()->nok();
$nokName = $nok['name'] ?? t('Your next of kin');
$warnings = array_values(array_filter($engine->warnings(), static fn (array $w): bool => $w['level'] === 'warn'));
$checklist = $engine->checklist();
$canArm = array_filter($checklist, static fn (array $i): bool => !$i['ok'] && $i['blocking']) === [];
$long = 'long';
$short = 'brief';

// How far along the six-day track we are, in nominal hours (0 to 144).
$progress = 0.0;
if ($cycle !== null && $plan !== []) {
    $nominal = ['email' => 0, 'reminder1' => 24, 'reminder2' => 48, 'verify' => 72, 'nok_reminder' => 120, 'fire' => 144];
    $now = Clock::now();
    $keys = array_keys($nominal);
    $progress = 144.0;
    for ($i = 0; $i < count($keys) - 1 && $cycle['stage'] !== 'fired'; $i++) {
        $from = $plan[$keys[$i]]['at'];
        $to = $plan[$keys[$i + 1]]['at'];
        if ($now < $to) {
            $share = $to > $from ? max(0, $now - $from) / ($to - $from) : 0;
            $progress = $nominal[$keys[$i]] + $share * ($nominal[$keys[$i + 1]] - $nominal[$keys[$i]]);
            break;
        }
    }
}
$dayNames = [1 => t('Monday'), 2 => t('Tuesday'), 3 => t('Wednesday'), 4 => t('Thursday'), 5 => t('Friday'), 6 => t('Saturday'), 7 => t('Sunday')];
$nextStep = null;
foreach ($plan as $key => $step) {
    if (!$step['done']) {
        $nextStep = $key;
        break;
    }
}

$notified = [];
foreach ($contacts as $contact) {
    if (isset($cycle['notified'][$contact['id']])) {
        $notified[] = $contact['name'];
    }
}

Web::$title = t('Death Plan Switch');
?>
<?php
$pilotClass = ['setup' => 'off', 'paused' => 'off', 'armed' => 'armed', 'overdue' => 'overdue', 'verify' => 'verify'][$mode] ?? 'fired';
?>
<?php if ($state['after_fire_at'] !== null): ?>
<div class="notice notice-warn">
  <p><strong><?= e(t('Your contacts have read their messages.')) ?></strong> <?= e(t('Any link or pointer in those messages has now been seen. If a message pointed to a document, move the document so the old link stops working, and change its password. Then update the message.')) ?></p>
  <form method="post" action="index.php"><?= Web::csrfField() ?><input type="hidden" name="action" value="dismiss_after_fire"><button class="link" type="submit"><?= e(t('I have done this')) ?></button></form>
</div>
<?php endif ?>

<?php if (count($warnings) === 1): ?>
<div class="notice notice-warn"><p><?= e($warnings[0]['text']) ?></p></div>
<?php elseif ($warnings): ?>
<div class="notice notice-warn">
  <ul>
<?php foreach ($warnings as $warning): ?>
    <li><?= e($warning['text']) ?></li>
<?php endforeach ?>
  </ul>
</div>
<?php endif ?>

<div class="dash">
<div class="col col-status">
<section class="card pilot pilot-<?= e($pilotClass) ?>" aria-live="polite">
<?php if ($mode === 'setup'): ?>
  <span class="pilot-dot" aria-hidden="true"></span>
  <h1 class="pilot-state"><?= e(t('Not armed')) ?></h1>
  <p class="pilot-line"><?= e(t('Nobody will be contacted while the switch is not armed.')) ?> <?= e($canArm ? t('Everything is in place: arm it when you are ready.') : t('Finish the list below, then arm it.')) ?></p>

<?php elseif ($mode === 'armed'): ?>
  <span class="pilot-dot" aria-hidden="true"></span>
  <h1 class="pilot-state"><?= e(t('Armed')) ?></h1>
  <p class="pilot-line"><?= e(t('Your next check-in email arrives on {date}.', ['date' => when($state['next_due_at'], $tz, $long)])) ?></p>
  <div class="actions">
    <form method="post" action="index.php"><?= Web::csrfField() ?><input type="hidden" name="action" value="checkin"><button class="button" type="submit"><?= e(t('Check in now')) ?></button></form>
  </div>

<?php elseif ($mode === 'paused'): ?>
  <span class="pilot-dot" aria-hidden="true"></span>
  <h1 class="pilot-state"><?= e(t('Paused')) ?></h1>
  <p class="pilot-line"><?= e(t('Until {date}. No emails are sent while the switch is paused, and nobody will be contacted. It starts again by itself.', ['date' => when($state['paused_until'], $tz, $long)])) ?></p>
  <div class="actions">
    <form method="post" action="index.php"><?= Web::csrfField() ?><input type="hidden" name="action" value="resume"><button class="button" type="submit"><?= e(t('End the pause now')) ?></button></form>
  </div>

<?php elseif ($mode === 'overdue'): ?>
  <span class="pilot-dot" aria-hidden="true"></span>
  <h1 class="pilot-state"><?= e(empty($cycle['reminders']) ? t('Time to check in') : t('Check-in overdue')) ?></h1>
  <p class="pilot-line"><?= e(t('You were emailed on {sent} and have not checked in. {name} will be asked whether you are all right on {asked}.', ['sent' => when($cycle['started_at'], $tz, $long), 'name' => $nokName, 'asked' => when($plan['verify']['at'], $tz, $long)])) ?></p>
  <div class="actions">
    <form method="post" action="index.php"><?= Web::csrfField() ?><input type="hidden" name="action" value="checkin"><button class="button button-big" type="submit"><?= e(t('Check in now')) ?></button></form>
  </div>

<?php elseif ($mode === 'verify'): ?>
  <span class="pilot-dot" aria-hidden="true"></span>
  <h1 class="pilot-state"><?= e(t('Your next of kin has been asked')) ?></h1>
  <p class="pilot-line"><?= e(empty($cycle['verify']['reached'])
      ? t('{name} was asked whether you are all right on {asked}, but the email could not be sent. Unless one of you answers, your other contacts receive their messages on {fire}.', ['name' => $nokName, 'asked' => when($cycle['verify']['asked_at'], $tz, $long), 'fire' => when($plan['fire']['at'], $tz, $long)])
      : t('{name} was asked whether you are all right on {asked}. Unless one of you answers, your other contacts receive their messages on {fire}.', ['name' => $nokName, 'asked' => when($cycle['verify']['asked_at'], $tz, $long), 'fire' => when($plan['fire']['at'], $tz, $long)])) ?></p>
  <div class="actions">
    <form method="post" action="index.php"><?= Web::csrfField() ?><input type="hidden" name="action" value="checkin"><button class="button button-big" type="submit"><?= e(t('Check in and call it off')) ?></button></form>
  </div>

<?php else: ?>
  <span class="pilot-dot" aria-hidden="true"></span>
  <h1 class="pilot-state"><?= e(t('The switch has fired')) ?></h1>
  <p class="pilot-line"><?= e(($cycle['fired_reason'] === 'nok_wrong'
      ? t('On {date}, {name} answered that something is wrong.', ['date' => when($cycle['fired_at'], $tz, $long), 'name' => $nokName])
      : t('On {date}, neither you nor {name} had confirmed you were well.', ['date' => when($cycle['fired_at'], $tz, $long), 'name' => $nokName]))
      . ' ' . ($notified ? t('Messages have gone to {names}.', ['names' => implode(', ', $notified)]) : t('No message has been delivered yet.'))) ?></p>
  <div class="actions">
    <form method="post" action="index.php"><?= Web::csrfField() ?><input type="hidden" name="action" value="checkin"><button class="button button-big" type="submit"><?= e(t('I am here: tell them it was a false alarm')) ?></button></form>
  </div>
<?php endif ?>
</section>

<?php if ($plan !== []): ?>
<div class="track card">
  <progress max="144" value="<?= e(round($progress, 1)) ?>" aria-hidden="true"></progress>
  <ol aria-label="<?= e($cycle === null ? t('What happens if you miss the next check-in') : t('Where this check-in stands')) ?>">
<?php
    $labels = [
        'email' => ['t0', t('Check-in email')],
        'reminder1' => ['t24', t('Reminder')],
        'reminder2' => ['t48', t('Last reminder')],
        'verify' => ['t72', t('Next of kin is asked')],
        'nok_reminder' => ['t120', t('Next of kin reminded')],
        'fire' => ['t144', t('Other contacts are told')],
    ];
    foreach ($labels as $key => [$class, $label]):
        $step = $plan[$key];
?>
    <li class="<?= $class ?><?= $step['done'] ? ' done' : '' ?><?= $key === $nextStep && $cycle !== null ? ' next' : '' ?>">
      <strong><?= e($label) ?></strong>
      <?= e(when($step['at'], $tz, $short)) ?>
    </li>
<?php endforeach ?>
  </ol>
<?php if ($cycle === null): ?>
  <p class="small muted"><?= e(t('This is what happens if you miss the next check-in. Your other contacts would be told 6 days after the email, at most 13 days after your last check-in.')) ?></p>
<?php endif ?>
</div>
<?php endif ?>

<?php if ($mode === 'setup'): ?>
<section class="card">
<h2><?= e(t('Before you can arm it')) ?></h2>
<ul class="checklist">
<?php foreach ($checklist as $item): ?>
  <li class="<?= $item['ok'] ? 'is-ok' : ($item['blocking'] ? 'is-todo' : 'is-optional') ?>">
    <span class="mark" aria-hidden="true"><?= $item['ok'] ? '✓' : ($item['blocking'] ? '✗' : '○') ?></span>
    <span><?= e($item['label']) ?><span class="visually-hidden"><?= $item['ok'] ? t(' (done)') : ($item['blocking'] ? t(' (to do)') : t(' (optional)')) ?></span></span>
<?php if (!$item['ok'] && $item['hint'] !== ''): ?>
    <span class="hint"><?= e($item['hint']) ?></span>
<?php endif ?>
  </li>
<?php endforeach ?>
</ul>
<div class="actions">
<?php if ($canArm): ?>
  <form method="post" action="index.php"><?= Web::csrfField() ?><input type="hidden" name="action" value="arm"><button class="button-big" type="submit"><?= e(t('Arm the switch')) ?></button></form>
<?php else: ?>
  <a class="button" href="index.php?p=contacts"><?= e(t('Go to contacts')) ?></a>
  <a class="button button-quiet" href="index.php?p=settings"><?= e(t('Go to settings')) ?></a>
<?php endif ?>
</div>
</section>
<?php endif ?>
</div>
<div class="col col-who">
<section class="card">
<h2><?= e(t('Who is told')) ?></h2>
<?php if ($contacts === []): ?>
<p><?= e(t('Nobody yet.')) ?> <a href="index.php?p=contacts"><?= e(t('Add your next of kin, and a technical helper if you like')) ?></a>.</p>
<?php else: ?>
<div class="rows">
<?php foreach ($contacts as $contact): ?>
  <div class="row">
    <div>
      <h3><a href="index.php?p=contact&amp;id=<?= e($contact['id']) ?>"><?= e($contact['name']) ?></a></h3>
      <p class="role"><?= e(Contacts::label((string) $contact['role_label'])) ?>. <?= e($contact['kind'] === 'nok'
          ? t('Asked first, after you miss a check-in and 72 hours of reminders.')
          : t('Sent their message if neither you nor your next of kin confirms you are well.')) ?></p>
    </div>
    <div class="side">
<?php if ($contact['status'] === 'confirmed'): ?>
      <span class="tag tag-ok"><?= e(t('Confirmed')) ?></span>
<?php elseif ($contact['status'] === 'declined'): ?>
      <span class="tag tag-bad"><?= e(t('Declined')) ?></span>
<?php else: ?>
      <span class="tag tag-warn"><?= e(t('Not confirmed')) ?></span>
<?php endif ?>
    </div>
  </div>
<?php endforeach ?>
</div>
<?php endif ?>
</section>

<section class="card">
<h2><?= e(t('Details')) ?></h2>
<dl class="facts">
  <dt><?= e(t('Last check-in')) ?></dt>
  <dd><?= e($state['last_checkin_at'] ? t('{when}, by {via}', ['when' => when($state['last_checkin_at'], $tz), 'via' => (string) $state['last_checkin_via']]) : t('None yet')) ?></dd>
  <dt><?= e(t('Weekly email')) ?></dt>
  <dd><?= e(t('{day}s at {hour} ({tz})', ['day' => $dayNames[(int) ($config['slot_dow'] ?? 1)] ?? '', 'hour' => hourLabel((int) ($config['slot_hour'] ?? 9)), 'tz' => $tz])) ?></dd>
  <dt><?= e(t('Scheduler last ran')) ?></dt>
  <dd><?= e($state['last_tick_at'] ? t('{when}, by {source}', ['when' => ucfirst(relative($state['last_tick_at'])), 'source' => $state['last_tick_source'] === 'cron' ? t('cron') : t('the trigger URL')]) : t('Never')) ?></dd>
</dl>
</section>
</div>
<?php if ($state['armed']): ?>
<div class="col col-away">
<section class="card">
<h2><?= e(t('Going away?')) ?></h2>
<p><?= e(t('Pause the switch for a set number of days. It starts again by itself, so it cannot be left off by accident. The longest pause is {days} days.', ['days' => Schedule::PAUSE_MAX_DAYS])) ?></p>
<form method="post" action="index.php">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="pause">
  <div class="inline">
    <label for="days"><?= e(t('Pause for')) ?></label>
    <input type="number" id="days" name="days" min="1" max="<?= Schedule::PAUSE_MAX_DAYS ?>" value="7" required>
    <span><?= e(t('days')) ?></span>
    <button class="button-quiet" type="submit"><?= e($mode === 'paused' ? t('Change the pause') : t('Pause the switch')) ?></button>
  </div>
</form>
</section>

<section class="card">
<h2><?= e(t('Disarm')) ?></h2>
<details>
  <summary><?= e(t('Turn the switch off')) ?></summary>
  <p><?= e(t('A disarmed switch sends no check-in emails and contacts nobody. It stays off until you arm it again. If you only need a break, pause it instead.')) ?></p>
  <form method="post" action="index.php">
    <?= Web::csrfField() ?>
    <input type="hidden" name="action" value="disarm">
    <div class="field">
      <label for="disarm-password"><?= e(t('Your password')) ?></label>
      <input type="password" id="disarm-password" name="password" autocomplete="current-password" required>
    </div>
    <button class="button-danger" type="submit"><?= e(t('Disarm the switch')) ?></button>
  </form>
</details>
</section>
</div>
<?php endif ?>
</div>
