<?php
/**
 * Where the next of kin answers "All fine" or "Something's wrong".
 *
 * As with the check-in, following the emailed link changes nothing. The
 * answer is only recorded when a button on this page is pressed.
 */
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

$engine = engine();
$token = Web::isPost() ? Web::post('t') : Web::get('t');
$lean = Web::get('a') === 'wrong' ? 'wrong' : 'fine';
$config = $engine->config();
$owner = (string) ($config['owner_name'] ?? '');
$answer = null;
$live = false;
$others = [];

if (Web::isPost()) {
    $answer = $engine->nokAnswer($token, Web::post('answer'));
} else {
    $live = $engine->verifyTokenIsLive($token);
}
if ($live || $answer === 'wrong') {
    foreach ($engine->contacts()->confirmed() as $contact) {
        if ($contact['kind'] !== 'nok') {
            $others[] = $contact['name'] . ' (' . $contact['role_label'] . ')';
        }
    }
}

$othersText = implode(', ', $others);
$fine = '<form method="post" action="respond.php"><input type="hidden" name="t" value="' . e($token) . '"><input type="hidden" name="answer" value="fine">'
    . '<h2>' . t('All fine') . '</h2><p>' . e(t('You have seen or spoken to {owner} and they are all right. Nobody else is contacted, and {owner} is told you answered.', ['owner' => $owner])) . '</p>'
    . '<div class="actions"><button class="button-big' . ($lean === 'fine' ? '' : ' button-quiet') . '" type="submit">' . t('All fine') . '</button></div></form>';
$wrong = '<form method="post" action="respond.php"><input type="hidden" name="t" value="' . e($token) . '"><input type="hidden" name="answer" value="wrong">'
    . '<h2>' . e(t("Something's wrong")) . '</h2><p>' . e(t('{owner} cannot run their business at the moment.', ['owner' => $owner])) . ' '
    . e($others ? t('These people are sent their instructions straight away: {names}.', ['names' => $othersText]) : t('The people they chose are sent their instructions straight away.'))
    . ' ' . e(t('You are sent yours too.')) . '</p>'
    . '<div class="actions"><button class="button-big' . ($lean === 'wrong' ? '' : ' button-quiet') . '" type="submit">' . e(t("Something's wrong")) . '</button></div></form>';

Web::$title = t('Death Plan Switch');
?>
<?php if ($answer === 'fine'): ?>
<h1><?= e(t('Thank you')) ?></h1>
<p><?= e(t('Your answer has been recorded. Nobody else has been contacted, and {owner} has been told that you answered.', ['owner' => $owner])) ?></p>

<?php elseif ($answer === 'wrong'): ?>
<h1><?= e(t('Thank you')) ?></h1>
<p><?= e(t('Your answer has been recorded.')) ?> <?= e($others ? t('These people have been sent their instructions: {names}.', ['names' => $othersText]) : t('The people {owner} chose have been sent their instructions.', ['owner' => $owner])) ?></p>
<p><?= e(t('You have been emailed a message from {owner} as well, with what to do next.', ['owner' => $owner])) ?></p>

<?php elseif ($live): ?>
<h1><?= e(t('Is {owner} all right?', ['owner' => $owner])) ?></h1>
<p><?= e(t('{owner} has not answered their weekly check-in or its reminders. You are their next of kin, so you are asked first. Nothing has been sent to anyone else.', ['owner' => $owner])) ?></p>
<?= $lean === 'wrong' ? $wrong . '<hr>' . $fine : $fine . '<hr>' . $wrong ?>
<hr>
<p class="small muted"><?= e(t('Not sure yet? You can come back to this page. If neither you nor {owner} answers by the time given in the email, the others are sent their instructions.', ['owner' => $owner])) ?></p>

<?php else: ?>
<h1><?= e(t('This link is no longer active')) ?></h1>
<p><?= e(t('It has either been used already, or the check it belonged to has been settled. You do not need to do anything.')) ?></p>
<?php endif ?>
