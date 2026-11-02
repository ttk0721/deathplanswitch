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
<p class="notice notice-warn"><?= $channelsWho !== null
    ? e(t('Anyone who can read a channel can follow its links. A check-in link in a shared channel lets them check in for {who}. Use channels only {who} can read: a direct message, a private ntfy topic, or SMS.', ['who' => $channelsWho]))
    : e(t('Anyone who can read a channel can follow its links. A check-in link in a shared channel lets them check in for you. Use channels only you can read: a direct message, a private ntfy topic, or SMS.')) ?></p>
<?php if ($channels): ?>
<ul class="channels">
<?php foreach ($channels as $channel): ?>
  <li>
    <strong><?= e($channel['label'] ?? '') ?></strong>
    <code><?= e(Channels::masked($channel)) ?></code>
<?php if (($channel['type'] ?? '') === 'webhook' && ($channel['secret'] ?? '') !== ''): ?>
    <details class="secret">
      <summary><?= e(t('Show signing secret')) ?></summary>
      <p class="small"><?= e(t('Use this to check the {header} header: {prefix} followed by the HMAC-SHA256 of the request body.', ['header' => 'X-DPS-Signature', 'prefix' => 'sha256='])) ?></p>
      <code><?= e($channel['secret']) ?></code>
    </details>
<?php endif ?>
    <form method="post" action="<?= e($self) ?>" class="inline">
      <?= Web::csrfField() ?>
      <input type="hidden" name="action" value="channel_test">
      <input type="hidden" name="channel_id" value="<?= e($channel['id'] ?? '') ?>">
      <button class="button-quiet" type="submit"><?= e(t('Test')) ?></button>
    </form>
    <form method="post" action="<?= e($self) ?>" class="inline">
      <?= Web::csrfField() ?>
      <input type="hidden" name="action" value="channel_remove">
      <input type="hidden" name="channel_id" value="<?= e($channel['id'] ?? '') ?>">
      <button class="link" type="submit" data-confirm="<?= e(t('Remove this channel?')) ?>"><?= e(t('Remove')) ?></button>
    </form>
  </li>
<?php endforeach ?>
</ul>
<?php else: ?>
<p><?= e(t('No channels yet.')) ?></p>
<?php endif ?>
<details>
  <summary><?= e(t('Add a channel')) ?></summary>
  <form method="post" action="<?= e($self) ?>">
    <?= Web::csrfField() ?>
    <input type="hidden" name="action" value="channel_add">
    <div class="field">
      <label for="channel_type"><?= e(t('Kind')) ?></label>
      <select id="channel_type" name="channel_type">
        <option value="apprise"><?= e(t('Apprise address (Discord, Telegram, ntfy, SMS...)')) ?></option>
        <option value="webhook"><?= e(t('Webhook (signed JSON)')) ?></option>
      </select>
    </div>
    <div class="field">
      <label for="channel_url"><?= e(t('Address')) ?></label>
      <input type="text" id="channel_url" name="channel_url" required maxlength="500" autocomplete="off">
      <span class="help"><?= e(t('Apprise examples: {examples}.', ['examples' => 'discord://webhook_id/token, tgram://bot_token/chat_id, ntfy://topic, serwersms://user:pass@48600100200, httpsms://key@phone'])) ?> <?= e(t('A webhook is an {scheme} address; each request is signed, and the signature is in the {header} header.', ['scheme' => 'https://', 'header' => 'X-DPS-Signature'])) ?></span>
    </div>
    <div class="field">
      <label for="channel_label"><?= e(t('Name')) ?> <span class="muted"><?= e(t('(optional)')) ?></span></label>
      <input type="text" id="channel_label" name="channel_label" maxlength="60" autocomplete="off">
    </div>
    <button type="submit"><?= e(t('Add channel')) ?></button>
  </form>
</details>
