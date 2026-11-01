<?php
/**
 * Where a contact confirms (or declines) their role, once at setup and
 * once a year after that. Their message is never shown here.
 */
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

$engine = engine();
$token = Web::isPost() ? Web::post('t') : Web::get('t');
$config = $engine->config();
$owner = (string) ($config['owner_name'] ?? '');
$result = null;
$contact = null;

if (Web::isPost()) {
    $result = $engine->contactAnswer($token, Web::post('answer') === 'yes');
} else {
    $contact = $engine->contacts()->forToken($token);
}
$purpose = (string) ($contact['token']['purpose'] ?? 'confirm');

Web::$title = t('Death Plan Switch');
?>
<?php if ($result !== null && $result['accepted']): ?>
<h1><?= e(t('Confirmed')) ?></h1>
<p><?= e(t('Thank you, {name}. {owner} has been told. There is nothing else to do.', ['name' => $result['contact']['name'], 'owner' => $owner])) ?></p>
<p><?= e(t('If {owner} ever stops checking in, you will hear from the same email address that sent you this request. Add it to your contacts so it is not mistaken for spam.', ['owner' => $owner])) ?></p>

<?php elseif ($result !== null): ?>
<h1><?= e(t('Done')) ?></h1>
<p><?= e(t("You will not be contacted by {owner}'s Death Plan Switch. {owner} has been told.", ['owner' => $owner])) ?></p>

<?php elseif ($contact !== null): ?>
<h1><?= e($purpose === 'reconfirm'
    ? t("Still happy to be {owner}'s {role}?", ['owner' => $owner, 'role' => Contacts::role($contact)])
    : t('{owner} has listed you as their {role}', ['owner' => $owner, 'role' => Contacts::role($contact)])) ?></h1>
<p><?= e(t('{owner} uses Death Plan Switch, a small app on their own server. Every week it asks {owner} to check in. If they stop, it emails the people they have chosen.', ['owner' => $owner])) ?></p>
<?php if ($contact['kind'] === 'nok'): ?>
<p><?= e(t('As next of kin you would be asked first. If {owner} misses a check-in and three days of reminders, you would get one email asking whether everything is all right, with two buttons to answer.', ['owner' => $owner])) ?></p>
<?php else: ?>
<p><?= e(t('If {owner} stops checking in and their next of kin cannot confirm they are well, you would get one email telling you what {owner} needs you to do.', ['owner' => $owner])) ?></p>
<?php endif ?>
<p><?= t('Confirming tells {owner} that {address} reaches you, and that you are willing.', ['owner' => e($owner), 'address' => '<strong>' . e($purpose === 'email_change' ? (string) $contact['pending_email'] : (string) $contact['email']) . '</strong>']) ?></p>
<form method="post" action="confirm.php">
  <input type="hidden" name="t" value="<?= e($token) ?>">
  <div class="actions">
    <button class="button-big" type="submit" name="answer" value="yes"><?= e(t('Yes, I confirm')) ?></button>
    <button class="button-quiet" type="submit" name="answer" value="no"><?= e(t('No, leave me out')) ?></button>
  </div>
</form>

<?php else: ?>
<h1><?= e(t('This link is no longer active')) ?></h1>
<p><?= e(t('It has either been used already or has expired. If you meant to confirm, ask the person who listed you to send a new request.')) ?></p>
<?php endif ?>
