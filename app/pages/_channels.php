<?php
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever included by a page
}

/**
 * Expects $channels (array) and $self (form action URL). Optional $channelsWho:
 * the name of the person whose channels these are, for the privacy warning.
 */
$channelsWho = isset($channelsWho) && $channelsWho !== '' ? (string) $channelsWho : null;
?>
<p class="notice notice-warn">Anyone who can read a channel can follow its links. A check-in link in a shared channel lets them check in for <?= $channelsWho !== null ? e($channelsWho) : 'you' ?>. Use channels only <?= $channelsWho !== null ? e($channelsWho) . ' can' : 'you can' ?> read: a direct message, a private ntfy topic, or SMS.</p>
<?php if ($channels): ?>
<ul class="channels">
<?php foreach ($channels as $channel): ?>
  <li>
    <strong><?= e($channel['label'] ?? '') ?></strong>
    <code><?= e(Channels::masked($channel)) ?></code>
<?php if (($channel['type'] ?? '') === 'webhook' && ($channel['secret'] ?? '') !== ''): ?>
    <details class="secret">
      <summary>Show signing secret</summary>
      <p class="small">Use this to check the <code>X-DPS-Signature</code> header: <code>sha256=</code> followed by the HMAC-SHA256 of the request body.</p>
      <code><?= e($channel['secret']) ?></code>
    </details>
<?php endif ?>
    <form method="post" action="<?= e($self) ?>" class="inline">
      <?= Web::csrfField() ?>
      <input type="hidden" name="action" value="channel_test">
      <input type="hidden" name="channel_id" value="<?= e($channel['id'] ?? '') ?>">
      <button class="button-quiet" type="submit">Test</button>
    </form>
    <form method="post" action="<?= e($self) ?>" class="inline">
      <?= Web::csrfField() ?>
      <input type="hidden" name="action" value="channel_remove">
      <input type="hidden" name="channel_id" value="<?= e($channel['id'] ?? '') ?>">
      <button class="link" type="submit" data-confirm="Remove this channel?">Remove</button>
    </form>
  </li>
<?php endforeach ?>
</ul>
<?php else: ?>
<p>No channels yet.</p>
<?php endif ?>
<details>
  <summary>Add a channel</summary>
  <form method="post" action="<?= e($self) ?>">
    <?= Web::csrfField() ?>
    <input type="hidden" name="action" value="channel_add">
    <div class="field">
      <label for="channel_type">Kind</label>
      <select id="channel_type" name="channel_type">
        <option value="apprise">Apprise address (Discord, Telegram, ntfy, SMS...)</option>
        <option value="webhook">Webhook (signed JSON)</option>
      </select>
    </div>
    <div class="field">
      <label for="channel_url">Address</label>
      <input type="text" id="channel_url" name="channel_url" required maxlength="500" autocomplete="off">
      <span class="help">Apprise examples: <code>discord://webhook_id/token</code>, <code>tgram://bot_token/chat_id</code>, <code>ntfy://topic</code>, <code>serwersms://user:pass@48600100200</code>, <code>httpsms://key@phone</code>. A webhook is an <code>https://</code> address; each request is signed, and the signature is in the <code>X-DPS-Signature</code> header.</span>
    </div>
    <div class="field">
      <label for="channel_label">Name <span class="muted">(optional)</span></label>
      <input type="text" id="channel_label" name="channel_label" maxlength="60" autocomplete="off">
    </div>
    <button type="submit">Add channel</button>
  </form>
</details>
