<?php
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

Web::requireOwner();
$engine = engine();

// Who is trusted, and in which order: plain forms, one press at a time.
if (Web::posted()) {
    $id = Web::post('id');
    $book = $engine->contacts();
    if ($book->find($id) !== null) {
        switch (Web::post('action')) {
            case 'trust':
                $book->setTrusted($id, true);
                Web::flash(t('Saved.'));
                break;
            case 'untrust':
                $book->setTrusted($id, false);
                Web::flash(t('Saved.'));
                break;
            case 'up':
                $book->move($id, -1);
                break;
            case 'down':
                $book->move($id, 1);
                break;
        }
    }
    Web::redirect('index.php?p=contacts');
}

$contacts = $engine->contacts()->all();
$trusted = $engine->contacts()->trusted();
$trustedIds = array_column($trusted, 'id');
$state = $engine->state();
$hasNok = $engine->contacts()->nok() !== null;
$hasTechnical = array_filter($contacts, static fn (array $c): bool => $c['kind'] === 'technical') !== [];
$reviewDue = $state['armed'] && $state['review_at'] !== null && Clock::now() >= $state['review_at'] + Schedule::YEAR;

Web::$title = t('Contacts: Death Plan Switch');
?>
<div class="page-top">
  <div>
    <h1><?= e(t('Contacts')) ?></h1>
    <p class="lede"><?= e(t('The people your switch writes to. Each one confirms by email before they count, and only confirmed contacts are ever written to.')) ?></p>
  </div>
  <div class="actions">
<?php if (!$hasNok): ?>
    <a class="button" href="index.php?p=contact&amp;kind=nok"><?= e(t('Add your next of kin')) ?></a>
<?php endif ?>
    <a class="button<?= $hasNok && !$hasTechnical ? '' : ' button-quiet' ?>" href="index.php?p=contact&amp;kind=technical"><?= e(t('Add a technical helper')) ?></a>
    <a class="button button-quiet" href="index.php?p=contact&amp;kind=legal"><?= e(t('Add a legal contact')) ?></a>
    <a class="button button-quiet" href="index.php?p=contact&amp;kind=other"><?= e(t('Add someone else')) ?></a>
  </div>
</div>

<?php if ($reviewDue): ?>
<div class="notice notice-warn"><p><?= e(t('Your yearly review is due.')) ?> <a href="index.php?p=review"><?= e(t('Confirm each contact still has what they need')) ?></a>.</p></div>
<?php endif ?>

<?php if ($contacts === []): ?>
<section class="card">
  <h2><?= e(t('Start with your next of kin')) ?></h2>
  <p><?= e(t('Your next of kin is asked first, privately, whether everything is all right. A technical helper is optional: someone who can keep your servers, accounts or projects running. Ask them before you add them.')) ?></p>
</section>
<?php else: ?>
<?php if ($trusted !== []): ?>
<section class="card">
  <h2><?= e(t('Trusted people')) ?></h2>
  <p><?= e(t('These people are asked whether everything is all right, one after another, starting with your next of kin. If any of them says all is fine, nobody else is asked. Only confirmed people are asked. How quickly they follow each other is set in Settings.')) ?></p>
  <div class="rows">
<?php foreach ($trusted as $i => $person): ?>
    <div class="row">
      <div>
        <h3><?= e((string) ($i + 1)) ?>. <a href="index.php?p=contact&amp;id=<?= e($person['id']) ?>"><?= e($person['name']) ?></a></h3>
        <p class="role"><?= e($i === 0 ? t('Asked first') : t('Asked after {name}', ['name' => $trusted[$i - 1]['name']])) ?></p>
      </div>
      <div class="side">
<?php if ($i > 0): ?>
<?php foreach ([['up', t('Move up'), $i > 1], ['down', t('Move down'), $i < count($trusted) - 1]] as [$action, $label, $show]): ?>
<?php if ($show): ?>
        <form method="post" action="index.php?p=contacts" class="inline-form"><?= Web::csrfField() ?><input type="hidden" name="id" value="<?= e($person['id']) ?>"><input type="hidden" name="action" value="<?= e($action) ?>"><button class="button-quiet" type="submit"><?= e($label) ?></button></form>
<?php endif ?>
<?php endforeach ?>
        <form method="post" action="index.php?p=contacts" class="inline-form"><?= Web::csrfField() ?><input type="hidden" name="id" value="<?= e($person['id']) ?>"><input type="hidden" name="action" value="untrust"><button class="button-quiet" type="submit"><?= e(t('Remove from trusted people')) ?></button></form>
<?php endif ?>
      </div>
    </div>
<?php endforeach ?>
  </div>
</section>
<?php endif ?>

<div class="contact-grid">
<?php foreach ($contacts as $contact): ?>
  <article class="card contact-card">
    <div>
      <h3><a href="index.php?p=contact&amp;id=<?= e($contact['id']) ?>"><?= e($contact['name']) ?></a></h3>
      <p class="role"><?= e(Contacts::label((string) $contact['role_label'])) ?></p>
    </div>
    <p><?= e($contact['email']) ?><?= $contact['phone'] !== '' ? ', ' . e($contact['phone']) : '' ?></p>
<?php if (!empty($contact['pending_email'])): ?>
    <p class="small muted"><?= e(t('Changing to {email} once they confirm it.', ['email' => $contact['pending_email']])) ?></p>
<?php endif ?>
    <div class="tags">
<?php if (in_array($contact['id'], $trustedIds, true)): ?>
      <span class="tag tag-ok"><?= e(t('Trusted person {n}', ['n' => array_search($contact['id'], $trustedIds, true) + 1])) ?></span>
<?php endif ?>
<?php if ($contact['status'] === 'confirmed'): ?>
      <span class="tag tag-ok"><?= e(t('Confirmed')) ?></span>
<?php elseif ($contact['status'] === 'declined'): ?>
      <span class="tag tag-bad"><?= e(t('Declined')) ?></span>
<?php elseif (!empty($contact['confirm_sent_at'])): ?>
      <span class="tag tag-warn"><?= e(t('Asked {when}', ['when' => relative($contact['confirm_sent_at'])])) ?></span>
<?php else: ?>
      <span class="tag tag-warn"><?= e(t('Not asked yet')) ?></span>
<?php endif ?>
<?php if (Messages::hasMarker(($contact['message']['body'] ?? '') . ($contact['verify_message']['body'] ?? ''))): ?>
      <span class="tag tag-bad"><?= e(t('Message not finished')) ?></span>
<?php elseif (empty($contact['has_needs_at'])): ?>
      <span class="tag tag-warn"><?= e(t('Not yet given what they need')) ?></span>
<?php else: ?>
      <span class="tag"><?= e(t('Has what they need')) ?></span>
<?php endif ?>
    </div>
<?php if (!in_array($contact['id'], $trustedIds, true)): ?>
    <form method="post" action="index.php?p=contacts"><?= Web::csrfField() ?><input type="hidden" name="id" value="<?= e($contact['id']) ?>"><input type="hidden" name="action" value="trust"><button class="button-quiet" type="submit"><?= e(t('Add to trusted people')) ?></button></form>
<?php endif ?>
  </article>
<?php endforeach ?>
</div>

<section class="card">
  <h2><?= e(t('Yearly review')) ?></h2>
  <p><?= e(t('Once a year you are asked to confirm each contact still has what they need. You can do it any time.')) ?> <a href="index.php?p=review"><?= e(t('Review now')) ?></a>.</p>
</section>
<?php endif ?>
