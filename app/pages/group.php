<?php
/**
 * Add or edit one group channel: its name, its address, and the message it is
 * sent when the switch fires.
 */
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

Web::requireOwner();
$engine = engine();
$book = new Groups(store());

$id = Web::get('id');
$isNew = $id === '';
$group = $isNew ? null : $book->find($id);
if (!$isNew && $group === null) {
    Web::flash(t('That group channel no longer exists.'), 'warn');
    Web::redirect('index.php?p=contacts#groups');
}
$self = 'index.php?p=group' . ($isNew ? '' : '&id=' . $group['id']);
$errors = [];
$typed = null;

if (Web::posted()) {
    $action = Web::post('action', 'save');

    if ($action === 'delete' && $group !== null) {
        $book->delete($group['id']);
        store()->log('group.deleted', t('The group channel {name} was deleted.', ['name' => $group['name']]));
        Web::flash(t('{name} has been deleted and will not be sent anything.', ['name' => $group['name']]));
        Web::redirect('index.php?p=contacts#groups');
    }

    if ($action === 'test' && $group !== null) {
        $result = Groups::sendTest(store(), new HttpNotifier(appriseUrl()), $group);
        Web::flash($result['message'], $result['ok'] ? 'ok' : 'bad');
        Web::redirect($self);
    }

    // The form always carries "correction_form", so an unticked box turns the correction off.
    $correction = Web::post('correction_form') === '1' ? isset($_POST['correction']) : null;
    [$saved, $errors] = Groups::fromInput($group, Web::post('name'), Web::post('channel_type'), Web::post('channel_url'), Web::post('text'),
        $correction, Web::post('correction_form') === '1' ? Web::post('correction_text') : null);
    if ($saved !== null) {
        try {
            $book->save($saved);
        } catch (\InvalidArgumentException $e) {
            $errors = [$e->getMessage()];
            $saved = null;
        }
    }
    if ($saved !== null) {
        if ($isNew) {
            store()->log('group.added', t('The group channel {name} was added.', ['name' => $saved['name']]));
        }
        $notes = [$isNew ? t('Saved. Use "Send a test message" to check that it works.') : t('Saved.')];
        $level = 'ok';
        if (Messages::looksLikeLogin($saved['text'])) {
            $notes[] = t('The message looks like it contains a password or login. Messages should say where things are and who to call, never the login itself.');
            $level = 'warn';
        }
        Web::flash(implode(' ', $notes), $level);
        Web::redirect('index.php?p=group&id=' . $saved['id']);
    }
    // Fall through with errors: show what was typed, never the address.
    $typed = ['name' => Web::post('name'), 'type' => Web::post('channel_type'), 'text' => Web::post('text'),
        'correction' => $correction ?? true, 'correction_text' => Web::post('correction_text')];
}

$name = (string) ($typed['name'] ?? $group['name'] ?? '');
$type = (string) ($typed['type'] ?? $group['channel']['type'] ?? 'apprise');
$text = (string) ($typed['text'] ?? $group['text'] ?? '');
$correctionOn = (bool) ($typed['correction'] ?? (($group['correction'] ?? true) !== false));
$correctionText = (string) ($typed['correction_text'] ?? $group['correction_text'] ?? '');
if ($correctionText === '') {
    $correctionText = Groups::defaultCorrection();
}
$parts = [];
if ($group !== null) {
    $vars = Messages::vars($engine->config(), $engine->state(), $engine->contacts()->all(), null);
    $parts = Groups::parts($group, $vars);
}

Web::$title = t('{page}: Death Plan Switch', ['page' => $isNew ? t('Add a group channel') : $group['name']]);
?>
<p class="back"><a href="index.php?p=contacts#groups"><?= e(t('All contacts')) ?></a></p>
<h1><?= e($isNew ? t('Add a group channel') : $group['name']) ?></h1>
<p class="lede"><?= e(t('A shared place, such as a team channel or a friends\' server, sent one message of yours when the switch fires. It needs no confirmation, and it is sent nothing else: no reminders, no questions and no vault. If you check in after it went out, it is sent your correction.')) ?></p>

<?php if ($errors): ?>
<div class="notice notice-bad" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach ?></ul></div>
<?php endif ?>

<div class="split">
<div class="split-main">
<form class="card" method="post" action="<?= e($self) ?>">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="save">

  <div class="field">
    <label for="name"><?= e(t('Name')) ?></label>
    <input type="text" id="name" name="name" value="<?= e($name) ?>" required maxlength="<?= Groups::NAME_MAX ?>" autocomplete="off">
    <span class="help"><?= e(t('For your own list, for example "Work – Slack" or "Friends\' server – Discord".')) ?></span>
  </div>
  <div class="field">
    <label for="channel_type"><?= e(t('Kind')) ?></label>
    <select id="channel_type" name="channel_type">
      <option value="apprise"<?= $type === 'apprise' ? ' selected' : '' ?>><?= e(t('Apprise address (Discord, Telegram, ntfy, SMS...)')) ?></option>
      <option value="webhook"<?= $type === 'webhook' ? ' selected' : '' ?>><?= e(t('Webhook (signed JSON)')) ?></option>
    </select>
  </div>
  <div class="field field-wide">
    <label for="channel_url"><?= e(t('Address')) ?></label>
    <input type="text" id="channel_url" name="channel_url"<?= $group === null ? ' required' : '' ?> maxlength="500" autocomplete="off">
    <span class="help"><?php if ($group !== null): ?><?= e(t('Saved: {address}. Leave this empty to keep it.', ['address' => Channels::masked((array) $group['channel'])])) ?> <?php endif ?><?= e(t('Apprise examples: {examples}.', ['examples' => 'discord://webhook_id/token, tgram://bot_token/chat_id, slack://token_a/token_b/token_c, ntfy://topic'])) ?> <?= e(t('A webhook is an {scheme} address; each request is signed, and the signature is in the {header} header.', ['scheme' => 'https://', 'header' => 'X-DPS-Signature'])) ?></span>
  </div>

  <h2><?= e(t('The message')) ?></h2>
  <p><?= e(t('Sent once, only when the switch fires, exactly as you write it here. Everyone who can read the channel will see it, so keep anything private for your contacts.')) ?> <strong><?= e(t('Never put a password or login in a message.')) ?></strong></p>
  <div class="field field-wide">
    <label for="text"><?= e(t('Message')) ?></label>
    <textarea id="text" name="text" rows="10" required maxlength="<?= Groups::TEXT_MAX ?>"><?= e($text) ?></textarea>
    <span class="help"><?= e(t('Plain text. Discord takes 2,000 characters in one message, Telegram 4,096, Slack 40,000 and anything else 4,000. A longer message is sent as several numbered parts, one after another.')) ?></span>
  </div>
  <details class="placeholders">
    <summary><?= e(t('Words that fill themselves in')) ?></summary>
    <dl class="facts">
<?php foreach (Groups::PLACEHOLDERS as $code): ?>
      <dt><code><?= e($code) ?></code></dt><dd><?= e(t(Messages::PLACEHOLDERS[$code])) ?></dd>
<?php endforeach ?>
    </dl>
    <p class="help"><?= e(t('Everyone in the channel reads this message, so the words that fill in your contacts\' names, addresses or phone numbers cannot be used here.')) ?></p>
  </details>

  <h2><?= e(t('Correction')) ?></h2>
  <input type="hidden" name="correction_form" value="1">
  <div class="tick">
    <input type="checkbox" id="correction" name="correction" value="1"<?= $correctionOn ? ' checked' : '' ?>>
    <label for="correction"><?= e(t('Send a correction if I check in after the message went out')) ?></label>
    <span class="help"><?= e(t('Sent once, only to a channel that had received the message, when your check-in shows it was a false alarm.')) ?></span>
  </div>
  <div class="field field-wide">
    <label for="correction_text"><?= e(t('Correction message')) ?></label>
    <textarea id="correction_text" name="correction_text" rows="3" maxlength="<?= Groups::TEXT_MAX ?>"><?= e($correctionText) ?></textarea>
  </div>

  <div class="actions">
    <button type="submit"><?= e($isNew ? t('Add group channel') : t('Save changes')) ?></button>
    <a class="button button-quiet" href="index.php?p=contacts#groups"><?= e(t('Cancel')) ?></a>
  </div>
</form>
</div>
<div class="split-side">

<?php if ($group !== null): ?>
<section class="card">
<h2><?= e(t('Test')) ?></h2>
<p><?= e(t('Sends a short, neutral test message to the channel now, so you can see that it arrives. It says nothing about the switch firing. At most one test a minute.')) ?></p>
<form method="post" action="<?= e($self) ?>">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="test">
  <button class="button-quiet" type="submit"><?= e(t('Send a test message')) ?></button>
</form>
<?php if (($group['channel']['type'] ?? '') === 'webhook' && ($group['channel']['secret'] ?? '') !== ''): ?>
<details class="secret">
  <summary><?= e(t('Show signing secret')) ?></summary>
  <p class="small"><?= e(t('Use this to check the {header} header: {prefix} followed by the HMAC-SHA256 of the request body.', ['header' => 'X-DPS-Signature', 'prefix' => 'sha256='])) ?></p>
  <code><?= e($group['channel']['secret']) ?></code>
</details>
<?php endif ?>
</section>

<section class="card">
<h2><?= e(t('What the channel would receive')) ?></h2>
<p><?= e(t('As saved. Names are filled in now; the number of days and the date are worked out when the message is sent.')) ?></p>
<?php foreach ($parts as $i => $part): ?>
<div class="preview">
  <header><?= e(count($parts) === 1 ? t('When the switch fires') : t('Part {n} of {total}', ['n' => $i + 1, 'total' => count($parts)])) ?><strong><?= e($group['name']) ?></strong></header>
  <pre><?= e($part) ?></pre>
</div>
<?php endforeach ?>
</section>

<section class="card">
<h2><?= e(t('Delete')) ?></h2>
<details>
  <summary><?= e(t('Remove this group channel from your switch')) ?></summary>
  <p><?= e(t('Nothing is sent to the channel, now or later.')) ?></p>
  <form method="post" action="<?= e($self) ?>">
    <?= Web::csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <button class="button-danger" type="submit"><?= e(t('Delete this group channel')) ?></button>
  </form>
</details>
</section>
<?php endif ?>
</div>
</div>
