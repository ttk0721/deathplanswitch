<?php
/**
 * Add or edit one contact: who they are, what they are sent, and whether
 * they have been given what they need.
 */
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

Web::requireOwner();
$engine = engine();
$book = $engine->contacts();
$state = $engine->state();

$id = Web::get('id');
$isNew = $id === '';
if ($isNew) {
    $kind = Web::get('kind', 'other');
    if (!isset(Contacts::KINDS[$kind])) {
        $kind = 'other';
    }
    if ($kind === 'nok' && $book->nok() !== null) {
        Web::flash(t('You already have a next of kin. Edit them, or delete them first.'), 'warn');
        Web::redirect('index.php?p=contacts');
    }
    $contact = Contacts::blank($kind);
} else {
    $contact = $book->find($id);
    if ($contact === null) {
        Web::flash(t('That contact no longer exists.'), 'warn');
        Web::redirect('index.php?p=contacts');
    }
}
$kind = $contact['kind'];
$self = 'index.php?p=contact' . ($isNew ? '&kind=' . $kind : '&id=' . $contact['id']);
$errors = [];

/** Would removing this contact leave an armed switch without a required role? */
$isLoadBearing = static function (array $contact) use ($state): bool {
    if (!$state['armed'] || $contact['status'] !== 'confirmed') {
        return false;
    }
    return $contact['kind'] === 'nok';
};

if (Web::posted()) {
    $action = Web::post('action', 'save');

    if ($action === 'delete' && !$isNew) {
        if ($isLoadBearing($contact)) {
            Web::flash(t('{name} cannot be deleted while the switch is armed, because the switch needs this role filled. Disarm it first, or add and confirm a replacement.', ['name' => $contact['name']]), 'bad');
            Web::redirect($self);
        }
        $book->delete($contact['id']);
        store()->log('contact.deleted', t('{name} ({role}) was deleted.', ['name' => $contact['name'], 'role' => Contacts::label((string) $contact['role_label'])]));
        Web::flash(t('{name} has been deleted and will not be contacted.', ['name' => $contact['name']]));
        Web::redirect('index.php?p=contacts');
    }

    if ($action === 'send' && !$isNew) {
        if ($engine->sendConfirmation($contact['id'])) {
            Web::flash(t('Confirmation email sent to {email}.', ['email' => $contact['pending_email'] ?: $contact['email']]));
        } else {
            Web::flash(t('The confirmation email could not be sent. Check the mail settings.'), 'bad');
        }
        Web::redirect($self);
    }

    if (in_array($action, ['channel_add', 'channel_remove', 'channel_test'], true) && !$isNew) {
        $channelId = Web::post('channel_id');
        if ($action === 'channel_add') {
            $channel = Channels::fromPost();
            if ($channel === null) {
                Web::flash(t('That channel was not accepted. Use an Apprise address such as {examples}, or a webhook that starts with {scheme}. For a plain web address, choose Webhook.', ['examples' => 'discord://webhook_id/token, tgram://bot_token/chat_id or ntfy://topic', 'scheme' => 'https://']), 'bad');
            } else {
                $done = store()->locked(static function () use ($book, $contact, $channel): bool {
                    $fresh = $book->find($contact['id']);
                    if ($fresh === null) {
                        return false;
                    }
                    $list = array_values(array_filter((array) ($fresh['channels'] ?? []), 'is_array'));
                    $list[] = $channel;
                    $fresh['channels'] = $list;
                    $book->save($fresh);
                    return true;
                });
                Web::flash($done ? t('Channel added. Use Test to check that it works.') : t('That contact no longer exists.'), $done ? 'ok' : 'warn');
            }
        } elseif ($action === 'channel_remove') {
            store()->locked(static function () use ($book, $contact, $channelId): void {
                $fresh = $book->find($contact['id']);
                if ($fresh === null) {
                    return;
                }
                $list = array_values(array_filter((array) ($fresh['channels'] ?? []), 'is_array'));
                $fresh['channels'] = array_values(array_filter($list, static fn (array $c): bool => ($c['id'] ?? '') !== $channelId));
                $book->save($fresh);
            });
            Web::flash(t('Channel removed.'), 'warn');
        } else {
            $found = null;
            $list = array_values(array_filter((array) (($book->find($contact['id']) ?? $contact)['channels'] ?? []), 'is_array'));
            foreach ($list as $c) {
                if ($channelId !== '' && ($c['id'] ?? '') === $channelId) {
                    $found = $c;
                }
            }
            if ($found === null) {
                Web::flash(t('That channel no longer exists.'), 'warn');
            } else {
                try {
                    (new HttpNotifier(appriseUrl()))->notify($found, new Notice(t('Test from Death Plan Switch'), t('If you can read this, this channel works.'), null), 'test');
                    Web::flash(t('Test sent to {name}.', ['name' => $found['label'] ?? t('the channel')]));
                } catch (\Throwable $e) {
                    Web::flash(t('The test failed: {reason}', ['reason' => $e->getMessage()]), 'bad');
                }
            }
        }
        Web::redirect($self);
    }

    // Save.
    $name = Web::post('name');
    $email = strtolower(Web::post('email'));
    $subject = Web::post('subject');
    $body = Web::post('body');

    if ($name === '') {
        $errors[] = t('Enter their name.');
    }
    if (!Web::validEmail($email)) {
        $errors[] = t('Enter a valid email address.');
    }
    if ($subject === '' || $body === '') {
        $errors[] = t('The message needs a subject and some text.');
    }
    foreach ($book->all() as $other) {
        if ($other['id'] !== $contact['id'] && strtolower((string) $other['email']) === $email) {
            $errors[] = t('{name} already uses that email address. Each contact needs their own.', ['name' => $other['name']]);
        }
    }
    $config = $engine->config();
    if (in_array($email, array_map('strtolower', $engine->ownerAddresses($config)), true)) {
        $errors[] = t('That is one of your own addresses. A contact has to be someone else.');
    }

    $contact['name'] = $name;
    $contact['phone'] = Web::post('phone');
    $contact['role_label'] = Web::post('role_label') ?: Contacts::KINDS[$kind];
    $contact['message'] = ['subject' => $subject, 'body' => $body];
    if ($kind === 'nok') {
        $contact['verify_message'] = [
            'subject' => Web::post('verify_subject') ?: Messages::verifyDefault()['subject'],
            'body' => Web::post('verify_body') ?: Messages::verifyDefault()['body'],
        ];
    }
    $ticked = isset($_POST['has_needs']);
    $contact['has_needs_at'] = $ticked ? ($contact['has_needs_at'] ?: Clock::now()) : null;

    $current = strtolower((string) $contact['email']);
    $pending = strtolower((string) ($contact['pending_email'] ?? ''));
    $sendNow = false;
    if ($errors === []) {
        if ($isNew) {
            $contact['email'] = $email;
            $sendNow = isset($_POST['send_now']);
        } elseif ($contact['status'] === 'confirmed') {
            if ($email === $current) {
                // Back to the confirmed address: drop any change that was waiting.
                if ($pending !== '') {
                    $contact['pending_email'] = null;
                    $contact['token'] = null;
                }
            } elseif ($email !== $pending) {
                // Keep writing to the address that confirmed until the new one confirms too.
                $contact['pending_email'] = $email;
                $sendNow = true;
            }
        } elseif ($email !== $current) {
            $contact['email'] = $email;
            $contact['status'] = 'unconfirmed';
            $contact['token'] = null; // a link sent to the old address must not confirm the new one
            $contact['confirm_sent_at'] = null;
        }

        $book->save($contact);
        if ($isNew) {
            store()->log('contact.added', t('{name} was added as {role}.', ['name' => $contact['name'], 'role' => Contacts::label((string) $contact['role_label'])]));
        }

        $notes = [t('Saved.')];
        $level = 'ok';
        if ($sendNow) {
            if ($engine->sendConfirmation($contact['id'])) {
                $notes[] = t('A confirmation email has gone to {email}.', ['email' => $email]);
            } else {
                $notes[] = t('The confirmation email could not be sent. Check the mail settings, then send it from this page.');
                $level = 'bad';
            }
        }
        $all = $body . ' ' . ($contact['verify_message']['body'] ?? '');
        if (Messages::hasMarker($all)) {
            $notes[] = t('The message still holds starter text in square brackets. Replace it with where things are.');
            $level = $level === 'bad' ? 'bad' : 'warn';
        }
        if (Messages::looksLikeLogin($all)) {
            $notes[] = t('The message looks like it contains a password or login. Messages should say where things are and who to call, never the login itself.');
            $level = $level === 'bad' ? 'bad' : 'warn';
        }
        Web::flash(implode(' ', $notes), $level);
        Web::redirect('index.php?p=contact&id=' . $contact['id']);
    }
    // Fall through with errors: show what was typed.
    $typedEmail = $email;
}

$previews = $isNew ? [] : $engine->preview($contact);
$needsHelp = [
    'nok' => t('For a next of kin, that usually means: they know this switch exists, they know who your other contacts are, and they know where your will and authority documents are kept.'),
    'technical' => t('For a technical helper, that usually means: they hold the password to your handover document, they can reach your servers and accounts, and they know what to look at first.'),
    'legal' => t('For a legal contact, that usually means: they hold whatever sealed copies you agreed on, and they know who your next of kin and technical helper are.'),
    'other' => t('Whatever this person would need in order to act on the message below.'),
][$kind];

Web::$title = t('{page}: Death Plan Switch', ['page' => $isNew ? t('Add a contact') : $contact['name']]);
?>
<p class="back"><a href="index.php?p=contacts"><?= e(t('All contacts')) ?></a></p>
<h1><?= $isNew ? e(['nok' => t('Add your next of kin'), 'technical' => t('Add a technical helper'), 'legal' => t('Add a legal contact'), 'other' => t('Add a contact')][$kind]) : e($contact['name']) ?></h1>

<?php if ($errors): ?>
<div class="notice notice-bad" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach ?></ul></div>
<?php endif ?>

<div class="split">
<div class="split-main">
<?php if (!$isNew): ?>
<div class="panel">
<?php if ($contact['status'] === 'confirmed'): ?>
  <p><span class="tag tag-ok"><?= e(t('Confirmed')) ?></span> <?= e(t('on {date}. They will be asked to reconfirm once a year.', ['date' => when($contact['reconfirmed_at'] ?? $contact['confirmed_at'], (string) $engine->config()['timezone'], 'date')])) ?></p>
<?php if (!empty($contact['pending_email'])): ?>
  <p><?= e(t('Waiting for them to confirm {new}. Until they do, the switch keeps using {old}.', ['new' => $contact['pending_email'], 'old' => $contact['email']])) ?></p>
  <form method="post" action="<?= e($self) ?>"><?= Web::csrfField() ?><input type="hidden" name="action" value="send"><button class="button-quiet" type="submit"><?= e(t('Send the confirmation email again')) ?></button></form>
<?php endif ?>
<?php elseif ($contact['status'] === 'declined'): ?>
  <p><span class="tag tag-bad"><?= e(t('Declined.')) ?></span> <?= e(t('{name} asked not to be a contact and will not be written to. If that was a mistake, speak to them, then send a new request.', ['name' => $contact['name']])) ?></p>
  <form method="post" action="<?= e($self) ?>"><?= Web::csrfField() ?><input type="hidden" name="action" value="send"><button class="button-quiet" type="submit"><?= e(t('Send a new confirmation email')) ?></button></form>
<?php else: ?>
  <p><span class="tag tag-warn"><?= e(t('Not confirmed.')) ?></span> <?= e(empty($contact['confirm_sent_at'])
      ? t('{name} has not been asked yet.', ['name' => $contact['name']])
      : t('A confirmation email went to {email} {when}. The link works for 30 days.', ['email' => $contact['email'], 'when' => relative($contact['confirm_sent_at'])])) ?> <?= e(t('Until they confirm, they will not be written to.')) ?></p>
  <form method="post" action="<?= e($self) ?>"><?= Web::csrfField() ?><input type="hidden" name="action" value="send"><button type="submit"><?= e(empty($contact['confirm_sent_at']) ? t('Send the confirmation email') : t('Send it again')) ?></button></form>
<?php endif ?>
</div>
<?php endif ?>

<form class="card" method="post" action="<?= e($self) ?>">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="save">

  <div class="field">
    <label for="name"><?= e(t('Name')) ?></label>
    <input type="text" id="name" name="name" value="<?= e($contact['name']) ?>" required maxlength="120" autocomplete="off">
  </div>
  <div class="field">
    <label for="email"><?= e(t('Email address')) ?></label>
    <input type="email" id="email" name="email" value="<?= e($typedEmail ?? ($contact['pending_email'] ?: $contact['email'])) ?>" required maxlength="254" autocomplete="off">
<?php if (!$isNew && $contact['status'] === 'confirmed'): ?>
    <span class="help"><?= e(t('If you change this, the new address has to confirm before it replaces the old one.')) ?></span>
<?php endif ?>
  </div>
  <div class="field">
    <label for="phone"><?= e(t('Phone')) ?> <span class="muted"><?= e(t('(optional)')) ?></span></label>
    <input type="tel" id="phone" name="phone" value="<?= e($contact['phone']) ?>" maxlength="40" autocomplete="off">
    <span class="help"><?= e(t('Never called or texted by the switch. It is shown to your other contacts so they can reach each other.')) ?></span>
  </div>
  <div class="field">
    <label for="role_label"><?= e(t('Role')) ?></label>
    <input type="text" id="role_label" name="role_label" value="<?= e($contact['role_label']) ?>" maxlength="60" autocomplete="off">
    <span class="help"><?= e(t('How this person is described to them and to your other contacts. Change it to fit, for example "Business partner" or "Accountant".')) ?></span>
  </div>

<?php if ($kind === 'nok'): ?>
  <h2><?= e(t('The question they are asked first')) ?></h2>
  <p><?= e(t('Sent when you have missed a check-in and 72 hours of reminders. Two answer links, "All fine" and "Something\'s wrong", are added beneath your text, with the time by which they need to answer.')) ?></p>
  <div class="field field-wide">
    <label for="verify_subject"><?= e(t('Subject')) ?></label>
    <input type="text" id="verify_subject" name="verify_subject" value="<?= e($contact['verify_message']['subject'] ?? '') ?>" maxlength="200">
  </div>
  <div class="field field-wide">
    <label for="verify_body"><?= e(t('Message')) ?></label>
    <textarea id="verify_body" name="verify_body" rows="5"><?= e($contact['verify_message']['body'] ?? '') ?></textarea>
  </div>
<?php endif ?>

  <h2><?= e($kind === 'nok' ? t('Their message if the switch fires') : t('Their message')) ?></h2>
  <p><?= e($kind === 'nok'
      ? t('Sent if they answer "Something\'s wrong", or if nobody answers for 72 hours. Your other contacts get theirs at the same moment.')
      : t('Sent only if neither you nor your next of kin confirms you are well.')) ?> <?= e(t('Say where things are and who to call.')) ?> <strong><?= e(t('Never put a password or login in a message.')) ?></strong></p>
  <div class="field field-wide">
    <label for="subject"><?= e(t('Subject')) ?></label>
    <input type="text" id="subject" name="subject" value="<?= e($contact['message']['subject']) ?>" required maxlength="200">
  </div>
  <div class="field field-wide">
    <label for="body"><?= e(t('Message')) ?></label>
    <textarea id="body" name="body" rows="10" required><?= e($contact['message']['body']) ?></textarea>
<?php if (Messages::hasMarker($contact['message']['body'])): ?>
    <span class="help"><?= e(t('Replace the text in [square brackets] with your own. The switch will not arm while it is there.')) ?></span>
<?php endif ?>
  </div>
  <details class="placeholders">
    <summary><?= e(t('Words that fill themselves in')) ?></summary>
    <dl class="facts">
<?php foreach (Messages::PLACEHOLDERS as $code => $meaning): ?>
      <dt><code><?= e($code) ?></code></dt><dd><?= e(t($meaning)) ?></dd>
<?php endforeach ?>
    </dl>
  </details>

  <h2><?= e(t('What they hold')) ?></h2>
  <div class="tick">
    <input type="checkbox" id="has_needs" name="has_needs" value="1"<?= !empty($contact['has_needs_at']) ? ' checked' : '' ?>>
    <label for="has_needs"><?= e($contact['name'] !== '' ? t('I have given {name} what they need', ['name' => $contact['name']]) : t('I have given this person what they need')) ?></label>
    <span class="help"><?= e($needsHelp) ?> <?= e(t('The switch holds no documents and no passwords: this tick is your own record that the handover outside the app is done.')) ?></span>
  </div>

<?php if ($isNew): ?>
  <div class="tick">
    <input type="checkbox" id="send_now" name="send_now" value="1" checked>
    <label for="send_now"><?= e(t('Email them a confirmation request now')) ?></label>
    <span class="help"><?= e(t('They get a short explanation and a link to confirm. Their message above is not shown to them.')) ?></span>
  </div>
<?php endif ?>

  <div class="actions">
    <button type="submit"><?= e($isNew ? t('Add contact') : t('Save changes')) ?></button>
    <a class="button button-quiet" href="index.php?p=contacts"><?= e(t('Cancel')) ?></a>
  </div>
</form>
</div>
<div class="split-side">

<?php if (!$isNew): ?>
<section class="card">
<h2><?= e(t('Channels')) ?></h2>
<p><?= e(t('Emails to {name} can also arrive on a channel. The message carries the subject and the link only.', ['name' => $contact['name']])) ?></p>
<p class="small muted"><?= e(t('SMS goes to the phone number above when an SMS address is set in Settings.')) ?></p>
<?php $channels = array_values(array_filter((array) ($contact['channels'] ?? []), 'is_array')); $channelsWho = (string) $contact['name']; require __DIR__ . '/_channels.php' ?>
</section>
<?php endif ?>

<?php if ($previews): ?>
<section class="card">
<h2><?= e(t('What {name} would receive', ['name' => $contact['name']])) ?></h2>
<p><?= e(t('As saved. Names are filled in now; the number of days and the date are worked out when the message is sent.')) ?> <?= $contact['status'] !== 'confirmed' ? e(t('Until they confirm, they receive nothing.')) : '' ?></p>
<?php foreach ($previews as $label => $email): ?>
<div class="preview">
  <header><?= e($label) ?><strong><?= e($email->subject) ?></strong></header>
  <pre><?= e($email->text()) ?></pre>
</div>
<?php endforeach ?>
</section>
<?php endif ?>

<?php if (!$isNew): ?>
<section class="card">
<h2><?= e(t('Delete')) ?></h2>
<details>
  <summary><?= e(t('Remove {name} from your switch', ['name' => $contact['name']])) ?></summary>
  <p><?= e(t('They will not be told, and will not be written to again.')) ?></p>
  <form method="post" action="<?= e($self) ?>">
    <?= Web::csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <button class="button-danger" type="submit"><?= e(t('Delete this contact')) ?></button>
  </form>
</details>
</section>
<?php endif ?>
</div>
</div>
