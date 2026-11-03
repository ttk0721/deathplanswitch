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

// Who is answering, and what their answer does, comes from this cycle's own chain, not from the contact list.
if (Web::isPost()) {
    $role = $engine->verifyAnswerer($token);
    $answer = $engine->nokAnswer($token, Web::post('answer'));
} else {
    $live = $engine->verifyTokenIsLive($token);
    $role = $live ? $engine->verifyAnswerer($token) : null;
}
// Only the next of kin, asked first, fires at once. Anyone else's "something's wrong" sends nothing early.
$firesNow = (bool) ($role['fires'] ?? false);
$laterPeople = $role !== null && $role['position'] !== null && $role['position'] < $role['size'];
$othersAsked = (bool) ($role['others_asked'] ?? false);
$firedNow = ($engine->state()['cycle']['stage'] ?? '') === 'fired';
$waitText = $laterPeople
    ? t('The other trusted people are still asked in turn, and nobody is sent anything early.')
    : t('Nobody is sent anything early. If {owner} does not check in and nobody answers "All fine" by the time given in the email, the people {owner} chose are sent their instructions.', ['owner' => $owner]);
if (($live && $firesNow) || ($answer === 'wrong' && $firedNow)) {
    foreach ($engine->contacts()->recipients() as $contact) {
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
    . (!$firesNow
        ? e($waitText)
        : e($others ? t('These people are sent their instructions straight away: {names}.', ['names' => $othersText]) : t('The people they chose are sent their instructions straight away.'))
            . ' ' . e(t('You are sent yours too.')))
    . '</p>'
    . '<div class="actions"><button class="button-big' . ($lean === 'wrong' ? '' : ' button-quiet') . '" type="submit">' . e(t("Something's wrong")) . '</button></div></form>';

Web::$title = t('Death Plan Switch');
?>
<?php if ($answer === 'fine'): ?>
<h1><?= e(t('Thank you')) ?></h1>
<p><?= e($othersAsked
    ? t('Your answer has been recorded. Nobody else will be asked. The others who were asked are told it was a false alarm, and {owner} has been told that you answered.', ['owner' => $owner])
    : t('Your answer has been recorded. Nobody else has been contacted, and {owner} has been told that you answered.', ['owner' => $owner])) ?></p>

<?php elseif ($answer === 'wrong' && !$firedNow): ?>
<h1><?= e(t('Thank you')) ?></h1>
<p><?= e(t('Your answer has been recorded.')) ?> <?= e($waitText) ?></p>

<?php elseif ($answer === 'wrong'): ?>
<h1><?= e(t('Thank you')) ?></h1>
<p><?= e(t('Your answer has been recorded.')) ?> <?= e($others ? t('These people have been sent their instructions: {names}.', ['names' => $othersText]) : t('The people {owner} chose have been sent their instructions.', ['owner' => $owner])) ?></p>
<p><?= e(t('You have been emailed a message from {owner} as well, with what to do next.', ['owner' => $owner])) ?></p>

<?php elseif ($live): ?>
<h1><?= e(t('Is {owner} all right?', ['owner' => $owner])) ?></h1>
<p><?= e($firesNow
    ? t('{owner} has not answered their weekly check-in or its reminders. You are their next of kin, so you are asked first. Nothing has been sent to anyone else.', ['owner' => $owner])
    : t('{owner} has not answered their weekly check-in or its reminders. You are one of the people they trust, so you are asked whether everything is all right. Nothing has been sent to anyone else.', ['owner' => $owner])) ?></p>
<?= $lean === 'wrong' ? $wrong . '<hr>' . $fine : $fine . '<hr>' . $wrong ?>
<hr>
<p class="small muted"><?= e(t('Not sure yet? You can come back to this page. If neither you nor {owner} answers by the time given in the email, the others are sent their instructions.', ['owner' => $owner])) ?></p>

<?php else: ?>
<h1><?= e(t('This link is no longer active')) ?></h1>
<p><?= e(t('It has either been used already, or the check it belonged to has been settled. You do not need to do anything.')) ?></p>
<?php endif ?>
