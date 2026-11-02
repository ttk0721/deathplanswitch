<?php
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

Web::requireOwner();
$engine = engine();
$store = store();
$config = $engine->config();
$outbox = new Outbox($store, new SmtpTransport());

if (Web::posted()) {
    switch (Web::post('action')) {
        case 'profile':
            $name = Web::post('owner_name');
            $tz = Web::post('timezone');
            $dow = (int) Web::post('slot_dow', '1');
            $hour = (int) Web::post('slot_hour', '9');
            if ($name === '' || !Setup::validTimezone($tz) || $dow < 1 || $dow > 7 || $hour < 0 || $hour > 23) {
                Web::flash(t('Enter your name, and choose a timezone, a day and an hour.'), 'bad');
                break;
            }
            $store->locked(static function () use ($store, $engine, $name, $tz, $dow, $hour): void {
                $config = $store->read('config');
                $slotChanged = [$config['timezone'], $config['slot_dow'], $config['slot_hour']] !== [$tz, $dow, $hour];
                $config['owner_name'] = $name;
                $config['timezone'] = $tz;
                $config['slot_dow'] = $dow;
                $config['slot_hour'] = $hour;
                $store->write('config', $config);
                if ($slotChanged) {
                    $state = $engine->state();
                    if ($state['armed'] && $state['cycle'] === null) {
                        $state['next_due_at'] = $engine->nextSlot($config, max(Clock::now(), (int) $state['paused_until']));
                        $engine->saveState($state);
                    }
                    $store->log('settings.slot', 'The weekly check-in time was changed to ' . Schedule::DAYS[$dow] . ' at ' . hourLabel($hour) . " ({$tz}).");
                }
            });
            Web::flash(t('Saved.'));
            break;

        case 'language_save':
            $language = Web::post('language');
            if (!isset(I18n::available()[$language])) {
                Web::flash(t('Choose one of the listed languages.'), 'bad');
                break;
            }
            $store->locked(static function () use ($store, $language): void {
                $config = $store->read('config');
                $config['language'] = $language;
                $store->write('config', $config);
            });
            Web::flash(t('Saved.'));
            break;

        case 'email_start':
            $which = Web::post('which') === 'backup_email' ? 'backup_email' : 'owner_email';
            $address = strtolower(Web::post('address'));
            $other = strtolower((string) ($config[$which === 'backup_email' ? 'owner_email' : 'backup_email'] ?? ''));
            if (!Web::validEmail($address)) {
                Web::flash(t('Enter a valid email address.'), 'bad');
            } elseif ($address === $other) {
                Web::flash(t('Your two addresses have to be different, ideally with different providers.'), 'bad');
            } elseif (Setup::startEmailCode($store, $outbox, $which, $address)) {
                Web::flash(t('A code has been emailed to {address}. Enter it below to confirm the address.', ['address' => $address]));
            } else {
                Web::flash(t('The code could not be sent to {address}. Check the address and the mail settings.', ['address' => $address]), 'bad');
            }
            break;

        case 'email_finish':
            $old = (string) $config['owner_email'];
            $result = Setup::finishEmailCode($store, Web::post('code'));
            Web::flash($result === true ? t('Address confirmed and saved.') : $result, $result === true ? 'ok' : 'bad');
            $now = (string) $store->read('config')['owner_email'];
            if ($result === true && $now !== $old) {
                // Tell the old address, in case the change was not made by the owner.
                $outbox->send($old, (new Email(t('Your Death Plan Switch now emails a different address')))
                    ->p(t('The main address for your Death Plan Switch was changed to {address}. Check-in emails will no longer come to this address.', ['address' => $now]))
                    ->p(t('If you did not make this change, sign in and change it back, then change your password.')));
            }
            break;

        case 'email_cancel':
            $config['pending_email'] = null;
            $store->write('config', $config);
            break;

        case 'backup_remove':
            $config['backup_email'] = '';
            $store->write('config', $config);
            Web::flash(t('Backup address removed. Reminders now go to your main address only.'), 'warn');
            break;

        case 'smtp':
            [$smtp, $errors] = Setup::smtpFromPost((array) ($config['smtp'] ?? []));
            if ($errors) {
                Web::flash(implode(' ', $errors), 'bad');
                break;
            }
            // Prove the new settings work before they replace the ones the switch depends on.
            if (!$outbox->send((string) $config['owner_email'], Messages::smtpTest(), 'owner', $smtp)) {
                $error = $store->read('mail')['last_send_error']['error'] ?? t('unknown error');
                Web::flash(t('Not saved: a test email could not be sent with those settings. The mail server said: {error}', ['error' => $error]), 'bad');
                break;
            }
            $config['smtp'] = $smtp;
            $store->write('config', $config);
            $store->log('settings.smtp', t('Mail settings were changed.'));
            Web::flash(t('Saved. A test email has been sent to {address}.', ['address' => $config['owner_email']]));
            break;

        case 'landing_save':
            $title = trim(Web::post('landing_title'));
            $text = trim(str_replace("\r\n", "\n", Web::post('landing_text')));
            $quote = trim((string) preg_replace('/\s+/u', ' ', Web::post('landing_quote')));
            $by = trim((string) preg_replace('/\s+/u', ' ', Web::post('landing_by')));
            $background = Web::post('landing_background', Landing::DEFAULT_BACKGROUND);
            if (mb_strlen($title) > 80 || mb_strlen($text) > 2000) {
                Web::flash(t('The title can be up to 80 characters and the text up to 2000.'), 'bad');
                break;
            }
            if (mb_strlen($quote) > 280 || mb_strlen($by) > 80) {
                Web::flash(t('The quote can be up to 280 characters and the name up to 80.'), 'bad');
                break;
            }
            if ($background !== 'own' && !in_array($background, Landing::BUILT_IN, true)) {
                $background = Landing::DEFAULT_BACKGROUND;
            }
            $upload = $_FILES['landing_image'] ?? null;
            $uploaded = null;
            if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ((int) $upload['error'] === UPLOAD_ERR_INI_SIZE || (int) $upload['error'] === UPLOAD_ERR_FORM_SIZE) {
                    Web::flash(t('The image can be up to 3 MB.'), 'bad');
                    break;
                }
                if ((int) $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $upload['tmp_name'])) {
                    Web::flash(t('The image could not be uploaded. Try again.'), 'bad');
                    break;
                }
                $uploaded = Landing::inspect((string) $upload['tmp_name']);
                if (is_string($uploaded)) {
                    Web::flash($uploaded, 'bad');
                    break;
                }
                $background = 'own';
            }
            $problem = $store->locked(static function () use ($store, $title, $text, $quote, $by, $background, $upload, $uploaded): ?string {
                $config = $store->read('config');
                $old = $config['landing']['image'] ?? null;
                $image = is_string($old) ? $old : null;
                if ($uploaded !== null) {
                    $image = Landing::store($store, (string) $upload['tmp_name'], $uploaded['ext'], $image);
                } elseif ($background === 'own' && Landing::imagePath($store, $image) === null) {
                    return t('Choose an image to upload.');
                }
                $landing = ['title' => $title, 'text' => $text, 'quote' => $quote, 'by' => $by, 'background' => $background];
                if ($image !== null) {
                    $landing['image'] = $image;
                }
                $config['landing'] = $landing;
                $store->write('config', $config);
                return null;
            });
            if ($problem !== null) {
                Web::flash($problem, 'bad');
                break;
            }
            Web::flash(t('Saved. This is what visitors see at the front of this install.'));
            break;

        case 'baseurl':
            $url = rtrim(Web::post('base_url'), '/');
            if (!preg_match('~^https?://[^\s/]+(/[^\s]*)?$~i', $url)) {
                Web::flash(t('Enter the full address of this install, starting with https://'), 'bad');
                break;
            }
            $config['base_url'] = $url;
            $store->write('config', $config);
            Web::flash(t('Saved. Links in emails will use this address.'));
            break;

        case 'probe':
            $result = Setup::probe($store, $engine, (string) $config['base_url']);
            if ($result['result'] === 'protected' || $result['result'] === 'outside') {
                Web::flash(t('Checked: the data folder cannot be read from the web.'));
            } elseif ($result['result'] === 'exposed') {
                Web::flash(t('Files in the data folder can be opened in a browser. See "Protecting the data folder" in the README.'), 'bad');
            } else {
                Web::flash(t('The server could not request its own address, so it could not check. Use the browser check below instead.'), 'warn');
            }
            break;

        case 'probe_browser':
            $found = Web::post('found');
            if ($found === 'protected' || $found === 'exposed') {
                Setup::recordBrowserProbe($store, $engine, $found);
                Web::flash($found === 'protected'
                    ? t('Checked from your browser: the data folder cannot be read from the web.')
                    : t('Your browser was able to open a file in the data folder. See "Protecting the data folder" in the README.'), $found === 'protected' ? 'ok' : 'bad');
            } else {
                Web::flash(t('Your browser could not complete the check.'), 'warn');
            }
            break;

        case 'rekey':
            $config['tick_key'] = bin2hex(random_bytes(20));
            $config['health_key'] = bin2hex(random_bytes(20));
            $store->write('config', $config);
            Web::flash(t('New addresses made. The old ones no longer work: update anything that used them.'), 'warn');
            break;

        case 'channel_add':
            $channel = Channels::fromPost();
            if ($channel === null) {
                Web::flash(t('That channel was not accepted. Use an Apprise address such as discord://webhook_id/token, tgram://bot_token/chat_id, ntfy://topic or serwersms://user:pass@48600100200, or a webhook that starts with https://. For a plain web address, choose Webhook.'), 'bad');
                break;
            }
            $store->locked(static function () use ($store, $channel): void {
                $config = $store->read('config');
                $config['channels'] = array_values(array_filter((array) ($config['channels'] ?? []), 'is_array'));
                $config['channels'][] = $channel;
                $store->write('config', $config);
            });
            Web::flash(t('Channel added. Use Test to check that it works.'));
            break;

        case 'channel_remove':
            $channelId = Web::post('channel_id');
            $store->locked(static function () use ($store, $channelId): void {
                $config = $store->read('config');
                $config['channels'] = array_values(array_filter(
                    (array) ($config['channels'] ?? []),
                    static fn ($c): bool => is_array($c) && ($c['id'] ?? '') !== $channelId
                ));
                $store->write('config', $config);
            });
            Web::flash(t('Channel removed.'), 'warn');
            break;

        case 'channel_test':
            $channelId = Web::post('channel_id');
            $found = null;
            foreach ((array) ($config['channels'] ?? []) as $c) {
                if (is_array($c) && $channelId !== '' && ($c['id'] ?? '') === $channelId) {
                    $found = $c;
                }
            }
            if ($found === null) {
                Web::flash(t('That channel no longer exists.'), 'warn');
                break;
            }
            try {
                (new HttpNotifier(appriseUrl()))->notify($found, new Notice(t('Test from Death Plan Switch'), t('If you can read this, this channel works.'), null), 'test');
                Web::flash(t('Test sent to {channel}.', ['channel' => $found['label'] ?? t('the channel')]));
            } catch (\Throwable $e) {
                Web::flash(t('The test failed: {error}', ['error' => $e->getMessage()]), 'bad');
            }
            break;

        case 'channels_save':
            $phone = Channels::phone(Web::post('owner_phone'));
            $template = Web::post('sms_template');
            $apprise = rtrim(Web::post('apprise_url'), '/');
            if ($template !== '' && (!str_contains($template, '{phone}') || !preg_match('~^[a-z][a-z0-9+.-]*://\S+$~i', $template) || Channels::normalize(['type' => 'apprise', 'url' => str_replace('{phone}', '1', $template)]) === null)) {
                Web::flash(t('The SMS address must be an Apprise address that contains {phone}, for example serwersms://user:pass@{phone}.', ['phone' => '{phone}']), 'bad');
                break;
            }
            if ($apprise !== '' && !preg_match('~^https?://[^\s/]+(/\S*)?$~i', $apprise)) {
                Web::flash(t('The Apprise server address must start with http:// or https://.'), 'bad');
                break;
            }
            $store->locked(static function () use ($store, $phone, $template, $apprise): void {
                $config = $store->read('config');
                $config['owner_phone'] = $phone;
                $config['sms_template'] = $template;
                $config['apprise_url'] = $apprise;
                $store->write('config', $config);
            });
            Web::flash(t('Saved.'));
            break;

        case 'password':
            $check = Web::tryPassword((string) ($_POST['current'] ?? ''));
            $new = (string) ($_POST['new'] ?? '');
            if ($check !== true) {
                Web::flash(t('Not changed. {reason}', ['reason' => $check]), 'bad');
            } elseif (($problem = Setup::passwordProblem($new)) !== null) {
                Web::flash($problem, 'bad');
            } else {
                $config['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
                $store->write('config', $config);
                $store->log('settings.password', t('The owner password was changed.'));
                Web::signIn();
                Web::flash(t('Password changed. Any other signed-in browser has been signed out.'));
            }
            break;
    }
    Web::redirect('index.php?p=settings');
}

$state = $engine->state();
$smtp = (array) ($config['smtp'] ?? []);
$pending = is_array($config['pending_email'] ?? null) && $config['pending_email']['expires_at'] > Clock::now() ? $config['pending_email'] : null;
$probe = (array) ($state['probe'] ?? []);
$probeUrl = Probe::url($store, (string) $config['base_url']);
$tickUrl = $engine->url($config, 'tick.php', ['key' => $config['tick_key'] ?? '']);
$healthUrl = $engine->url($config, 'health.php', ['key' => $config['health_key'] ?? '']);

Web::$title = t('Settings: Death Plan Switch');
?>
<h1><?= e(t('Settings')) ?></h1>

<div class="masonry">
<section class="card">
<h2>Język / Language</h2>
<form method="post" action="index.php?p=settings">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="language_save">
  <div class="field">
    <label for="language">Język / Language</label>
    <select id="language" name="language">
      <?php foreach (I18n::available() as $code => $info): ?>
      <option value="<?= e($code) ?>"<?= lang() === $code ? ' selected' : '' ?>><?= e($info['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="actions"><button type="submit"><?= e(t('Save')) ?></button></div>
</form>
</section>

<section class="card">
<h2><?= e(t('You, and your weekly email')) ?></h2>
<form method="post" action="index.php?p=settings">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="profile">
  <div class="field">
    <label for="owner_name"><?= e(t('Your name')) ?></label>
    <input type="text" id="owner_name" name="owner_name" value="<?= e($config['owner_name']) ?>" required maxlength="120">
    <span class="help"><?= e(t('As your contacts know you. It appears in every message they receive.')) ?></span>
  </div>
  <div class="field">
    <label for="timezone"><?= e(t('Timezone')) ?></label>
    <select id="timezone" name="timezone">
<?php foreach (Setup::timezones() as $tz): ?>
      <option<?= $tz === $config['timezone'] ? ' selected' : '' ?>><?= e($tz) ?></option>
<?php endforeach ?>
    </select>
  </div>
  <div class="field">
    <span class="label" id="slot-label"><?= e(t('Send my check-in email every')) ?></span>
    <div class="inline" role="group" aria-labelledby="slot-label">
      <select name="slot_dow" aria-label="<?= e(t('Day')) ?>">
<?php foreach (Schedule::DAYS as $n => $day): ?>
        <option value="<?= $n ?>"<?= $n === (int) $config['slot_dow'] ? ' selected' : '' ?>><?= e(t($day)) ?></option>
<?php endforeach ?>
      </select>
      <span><?= e(t('at')) ?></span>
      <select name="slot_hour" aria-label="<?= e(t('Hour')) ?>">
<?php for ($h = 0; $h < 24; $h++): ?>
        <option value="<?= $h ?>"<?= $h === (int) $config['slot_hour'] ? ' selected' : '' ?>><?= e(hourLabel($h)) ?></option>
<?php endfor ?>
      </select>
    </div>
    <span class="help"><?= e(t('Pick a time you reliably read email. The interval is fixed at one week.')) ?></span>
  </div>
  <div class="actions"><button type="submit"><?= e(t('Save')) ?></button></div>
</form>
</section>

<section class="card">
<h2><?= e(t('Your email addresses')) ?></h2>
<p><?= e(t('Check-in emails go to your main address. Reminders also go to the backup, so one failed mailbox cannot set the switch off. Use a different provider for the backup.')) ?></p>
<?php if ($pending): ?>
<div class="panel">
  <h3><?= e(t('Enter the code sent to {address}', ['address' => $pending['address']])) ?></h3>
  <form method="post" action="index.php?p=settings">
    <?= Web::csrfField() ?>
    <input type="hidden" name="action" value="email_finish">
    <div class="inline">
      <label for="code" class="visually-hidden"><?= e(t('Code')) ?></label>
      <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" required>
      <button type="submit"><?= e(t('Confirm address')) ?></button>
    </div>
  </form>
  <form method="post" action="index.php?p=settings"><?= Web::csrfField() ?><input type="hidden" name="action" value="email_cancel"><button class="link" type="submit"><?= e(t('Cancel this change')) ?></button></form>
</div>
<?php endif ?>
<dl class="facts">
  <dt><?= e(t('Main')) ?></dt><dd><?= e($config['owner_email']) ?></dd>
  <dt><?= e(t('Backup')) ?></dt><dd><?= !empty($config['backup_email']) ? e($config['backup_email']) : e(t('None')) ?></dd>
</dl>
<details>
  <summary><?= e(t('Change an address')) ?></summary>
  <form method="post" action="index.php?p=settings">
    <?= Web::csrfField() ?>
    <input type="hidden" name="action" value="email_start">
    <div class="field">
      <label for="which"><?= e(t('Which one')) ?></label>
      <select id="which" name="which">
        <option value="backup_email"><?= e(t('Backup address')) ?></option>
        <option value="owner_email"><?= e(t('Main address')) ?></option>
      </select>
    </div>
    <div class="field">
      <label for="address"><?= e(t('New address')) ?></label>
      <input type="email" id="address" name="address" required maxlength="254">
      <span class="help"><?= e(t('A code is emailed to it. The change takes effect when you enter the code here.')) ?></span>
    </div>
    <button type="submit"><?= e(t('Send the code')) ?></button>
  </form>
<?php if (!empty($config['backup_email'])): ?>
  <form method="post" action="index.php?p=settings"><?= Web::csrfField() ?><input type="hidden" name="action" value="backup_remove"><button class="link" type="submit"><?= e(t('Remove the backup address')) ?></button></form>
<?php endif ?>
</details>
</section>

<section class="card">
<h2><?= e(t('Notification channels')) ?></h2>
<p><?= e(t('Every email to you can also arrive on a channel such as Discord, Telegram, ntfy, SMS or a webhook. The message carries the subject and the link only, never the text of the email. A step counts as delivered when the email or any channel gets through.')) ?></p>
<?php $channels = array_values(array_filter((array) ($config['channels'] ?? []), 'is_array')); $self = 'index.php?p=settings'; require __DIR__ . '/_channels.php' ?>
<h3><?= e(t('SMS and Apprise server')) ?></h3>
<form method="post" action="index.php?p=settings">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="channels_save">
  <div class="field">
    <label for="owner_phone"><?= e(t('Your phone number')) ?> <span class="muted"><?= e(t('(optional)')) ?></span></label>
    <input type="text" id="owner_phone" name="owner_phone" value="<?= e($config['owner_phone'] ?? '') ?>" maxlength="40" autocomplete="off">
    <span class="help"><?= e(t('With the country code, for example +48 600 100 200.')) ?></span>
  </div>
  <div class="field">
    <label for="sms_template"><?= e(t('SMS address')) ?> <span class="muted"><?= e(t('(optional)')) ?></span></label>
    <input type="text" id="sms_template" name="sms_template" value="<?= e($config['sms_template'] ?? '') ?>" maxlength="500" autocomplete="off">
    <span class="help"><?= t('An Apprise address with {phone} where the number goes, for example {example}. It is used for you and for contacts with a phone number.', ['phone' => '<code>{phone}</code>', 'example' => '<code>serwersms://user:pass@{phone}</code>']) ?></span>
  </div>
  <div class="field">
    <label for="apprise_url"><?= e(t('Apprise server')) ?> <span class="muted"><?= e(t('(optional)')) ?></span></label>
    <input type="text" id="apprise_url" name="apprise_url" value="<?= e($config['apprise_url'] ?? '') ?>" maxlength="300" autocomplete="off">
    <span class="help"><?= e(t('Leave empty to use the one that came with this install.')) ?></span>
  </div>
  <div class="actions"><button type="submit"><?= e(t('Save')) ?></button></div>
</form>
</section>

<section class="card">
<h2><?= e(t('Mail server')) ?></h2>
<form method="post" action="index.php?p=settings">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="smtp">
<?php require __DIR__ . '/_smtp_fields.php' ?>
  <div class="actions"><button type="submit"><?= e(t('Test and save')) ?></button></div>
  <p class="small muted"><?= e(t('The new settings are saved only if a test email to {address} is accepted.', ['address' => $config['owner_email']])) ?></p>
</form>
</section>

<section class="card">
<h2><?= e(t('Scheduler')) ?></h2>
<p><?= e(t('The switch does nothing by itself. Every 15 minutes, something has to run it.')) ?> <?= e($state['last_tick_at']
    ? ($state['last_tick_source'] === 'cron'
        ? t('It last ran {when}, by cron.', ['when' => relative($state['last_tick_at'])])
        : t('It last ran {when}, by the trigger address.', ['when' => relative($state['last_tick_at'])]))
    : t('It has never run.')) ?></p>
<?php if (\DPS\inDocker()): ?>
<p><?= t('The {service} service runs the switch every 15 minutes. To see its runs, use {command}.', ['service' => '<code>scheduler</code>', 'command' => '<code>docker compose logs scheduler</code>']) ?></p>
<?php else: ?>
<h3><?= e(t('Crontab line')) ?></h3>
<p><?= t('Goes in the crontab of the user the web pages run as. Open it with {command}.', ['command' => '<code>' . e(Web::crontabCommand()) . '</code>']) ?></p>
<pre class="cron"><?= e(Web::cronLine()) ?></pre>
<?php if (Web::phpCli() === null): ?>
<p class="small"><?= t('The path to PHP could not be detected. Replace {php} at the start of the command with the full path your host gives you.', ['php' => '<code>php</code>']) ?></p>
<?php endif ?>
<h3><?= e(t('No cron on your hosting?')) ?></h3>
<p><?= e(t('Have any outside scheduler request this address every 15 minutes instead (hourly at the least). It does the same job. Treat the address as a secret.')) ?></p>
<pre class="cron"><?= e($tickUrl) ?></pre>
<?php endif ?>
<h3><?= e(t('Be told when it stops')) ?></h3>
<p><?= e(t('If the scheduler stops, the switch cannot tell you. Point an uptime monitor at this address. It answers 200 when all is well, and 503 when the scheduler has stopped, mail is failing or the data folder is exposed. It reveals nothing else.')) ?></p>
<pre class="cron"><?= e($healthUrl) ?></pre>
<?php if (\DPS\inDocker()): ?>
<p><?= t('Set {variable} in {file} to have every run reported to Healthchecks.io.', ['variable' => '<code>HEARTBEAT_URL</code>', 'file' => '<code>.env</code>']) ?></p>
<?php endif ?>
<form method="post" action="index.php?p=settings"><?= Web::csrfField() ?><input type="hidden" name="action" value="rekey"><button class="link" type="submit" data-confirm="<?= e(t('Make new addresses? The current ones will stop working.')) ?>"><?= e(t('Make new secret addresses')) ?></button></form>
</section>

<section class="card">
<h2><?= e(t('Data folder')) ?></h2>
<p><?= t('Your contacts and their messages are stored as files in {folder}. They must not be readable from the web. The switch tests this every day by requesting one of its own files.', ['folder' => '<code>' . e($store->dir()) . '</code>']) ?></p>
<dl class="facts">
  <dt><?= e(t('Last result')) ?></dt>
  <dd><?php
    $label = [
        'protected' => t('Blocked from the web'),
        'outside' => t('Outside the web folder'),
        'exposed' => t('Readable from the web'),
        'unknown' => !empty($probe['browser_ok_at']) ? t('Blocked (checked from your browser)') : t('Could not be checked by the server'),
    ][$probe['result'] ?? ''] ?? t('Not checked yet');
    echo e($label);
    if (!empty($probe['checked_at'])) {
        echo ', ' . e(relative((int) $probe['checked_at']));
    }
  ?></dd>
</dl>
<div class="actions">
  <form method="post" action="index.php?p=settings"><?= Web::csrfField() ?><input type="hidden" name="action" value="probe"><button class="button-quiet" type="submit"><?= e(t('Check again from the server')) ?></button></form>
<?php if ($probeUrl !== null): ?>
  <form method="post" action="index.php?p=settings" data-probe-url="<?= e($probeUrl) ?>" data-probe-secret="<?= e(Probe::secret($store)) ?>">
    <?= Web::csrfField() ?>
    <input type="hidden" name="action" value="probe_browser">
    <input type="hidden" name="found" value="">
    <button class="button-quiet" type="submit"><?= e(t('Check from this browser')) ?></button>
  </form>
<?php endif ?>
</div>
</section>

<section class="card">
<h2><?= e(t('Public page')) ?></h2>
<?php
$landingSaved = (array) ($config['landing'] ?? []);
$landingChoice = Landing::choice($store, $landingSaved);
$hasOwnImage = Landing::imagePath($store, $landingSaved['image'] ?? null) !== null;
?>
<form method="post" action="index.php?p=settings" enctype="multipart/form-data">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="landing_save">
  <div class="field field-wide">
    <label for="landing_quote"><?= e(t('Quote')) ?></label>
    <textarea id="landing_quote" name="landing_quote" rows="3" maxlength="280" class="short"><?= e($landingSaved['quote'] ?? '') ?></textarea>
    <span class="help"><?= e(t('Shown large in the middle of the page, in your own words. Up to 280 characters.')) ?></span>
  </div>
  <div class="field">
    <label for="landing_by"><?= e(t('Name under the quote')) ?></label>
    <input type="text" id="landing_by" name="landing_by" maxlength="80" value="<?= e($landingSaved['by'] ?? '') ?>" placeholder="<?= e((string) ($config['owner_name'] ?? '')) ?>">
  </div>
  <fieldset class="backgrounds">
    <legend><?= e(t('Background')) ?></legend>
    <div class="background-grid">
<?php foreach (Landing::builtIn() as $id => $label): ?>
      <label class="background-card">
        <input type="radio" name="landing_background" value="<?= e($id) ?>"<?= $landingChoice === $id ? ' checked' : '' ?>>
        <img src="assets/landing/<?= e($id) ?>.svg" alt="" width="160" height="100" loading="lazy">
        <span><?= e($label) ?></span>
      </label>
<?php endforeach ?>
      <label class="background-card">
        <input type="radio" name="landing_background" value="own"<?= $landingChoice === 'own' ? ' checked' : '' ?>>
<?php if ($hasOwnImage): ?>
        <img src="<?= e(Landing::src('own', $landingSaved)) ?>" alt="" width="160" height="100" loading="lazy">
<?php else: ?>
        <span class="background-empty" aria-hidden="true"></span>
<?php endif ?>
        <span><?= e(t('Own image')) ?></span>
      </label>
    </div>
    <div class="field">
      <label for="landing_image"><?= e(t('Upload your own image')) ?></label>
      <input type="file" id="landing_image" name="landing_image" accept="image/jpeg,image/png,image/webp">
      <span class="help"><?= e(t('JPEG, PNG or WebP, up to 3 MB. A calm picture works best: text is placed over it.')) ?></span>
    </div>
  </fieldset>
  <div class="field">
    <label for="landing_title"><?= e(t('Title')) ?></label>
    <input type="text" id="landing_title" name="landing_title" maxlength="80" value="<?= e($landingSaved['title'] ?? '') ?>" placeholder="Death Plan Switch">
  </div>
  <div class="field">
    <label for="landing_text"><?= e(t('Text')) ?></label>
    <textarea id="landing_text" name="landing_text" rows="5" maxlength="2000" placeholder="<?= e(t('This switch is not active.')) ?>"><?= e($landingSaved['text'] ?? '') ?></textarea>
    <span class="help"><?= e(t('What visitors see at this address before the switch has fired. Leave empty for the defaults. A blank line starts a new paragraph.')) ?></span>
  </div>
  <div class="actions">
    <button type="submit"><?= e(t('Save')) ?></button>
    <a class="button button-quiet" href="index.php?p=public" target="_blank" rel="noopener"><?= e(t('Preview the page')) ?></a>
  </div>
</form>
</section>

<section class="card">
<h2><?= e(t('Address of this install')) ?></h2>
<form method="post" action="index.php?p=settings">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="baseurl">
  <div class="field">
    <label for="base_url"><?= e(t('Web address')) ?></label>
    <input type="url" id="base_url" name="base_url" value="<?= e($config['base_url']) ?>" required>
    <span class="help"><?= e(t('Used to build the links in emails. Change it if you move the install.')) ?></span>
  </div>
  <div class="actions"><button type="submit"><?= e(t('Save')) ?></button></div>
</form>
</section>

<section class="card">
<h2><?= e(t('Password')) ?></h2>
<form method="post" action="index.php?p=settings">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="password">
  <div class="field">
    <label for="current"><?= e(t('Current password')) ?></label>
    <input type="password" id="current" name="current" autocomplete="current-password" required>
  </div>
  <div class="field">
    <label for="new"><?= e(t('New password')) ?></label>
    <input type="password" id="new" name="new" autocomplete="new-password" minlength="<?= Setup::MIN_PASSWORD ?>" required>
    <span class="help"><?= e(t('At least {count} characters.', ['count' => Setup::MIN_PASSWORD])) ?></span>
  </div>
  <div class="actions"><button type="submit"><?= e(t('Change password')) ?></button></div>
</form>
</section>
</div>
