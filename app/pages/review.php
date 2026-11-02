<?php
/**
 * The yearly review: the owner confirms each contact still has what they need.
 */
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

Web::requireOwner();
$engine = engine();

if (Web::posted()) {
    $ids = array_values(array_filter((array) ($_POST['has'] ?? []), 'is_string'));
    $engine->recordReview($ids);
    $confirmed = $engine->contacts()->confirmed();
    $missing = array_filter($confirmed, static fn (array $c): bool => !in_array($c['id'], $ids, true));
    if ($missing) {
        Web::flash(t('Saved. Still unticked: {names}. The review is complete when every confirmed contact is ticked.', ['names' => implode(', ', array_column($missing, 'name'))]), 'warn');
        Web::redirect('index.php?p=review');
    }
    Web::flash(t('Review complete. You will be asked again in a year.'));
    Web::redirect('index.php?p=contacts');
}

$config = $engine->config();
$state = $engine->state();
$contacts = $engine->contacts()->all();

Web::$title = t('Yearly review: Death Plan Switch');
?>
<p class="small"><a href="index.php?p=contacts"><?= e(t('All contacts')) ?></a></p>
<h1><?= e(t('Yearly review')) ?></h1>
<p class="lede"><?= e(t('Things drift. A password gets changed, a document moves, someone gets a new phone. Check with each person, then tick them off.')) ?></p>
<?php if ($state['review_at']): ?>
<p class="muted"><?= e(t('Last completed on {date}.', ['date' => when($state['review_at'], (string) $config['timezone'], 'date')])) ?></p>
<?php endif ?>

<?php if ($contacts === []): ?>
<p><?= e(t('You have no contacts yet.')) ?> <a href="index.php?p=contacts"><?= e(t('Add them first')) ?></a>.</p>
<?php else: ?>
<form method="post" action="index.php?p=review">
  <?= Web::csrfField() ?>
<?php foreach ($contacts as $contact): ?>
  <div class="tick">
    <input type="checkbox" id="has-<?= e($contact['id']) ?>" name="has[]" value="<?= e($contact['id']) ?>">
    <label for="has-<?= e($contact['id']) ?>"><?= e(t('{name} still has what they need', ['name' => $contact['name']])) ?></label>
    <span class="help"><?= e($contact['role_label']) ?>, <?= e($contact['email']) ?><?= $contact['phone'] !== '' ? ', ' . e($contact['phone']) : '' ?>.
      <?= $contact['status'] !== 'confirmed' ? e(t('Not confirmed, so not written to.')) : '' ?>
      <?= !empty($contact['has_needs_at']) ? e(t('Last ticked {date}.', ['date' => when($contact['has_needs_at'], (string) $config['timezone'], 'date')])) : e(t('Never ticked.')) ?>
      <a href="index.php?p=contact&amp;id=<?= e($contact['id']) ?>"><?= e(t('Read their message')) ?></a></span>
  </div>
<?php endforeach ?>
  <div class="actions">
    <button type="submit"><?= e(t('Save the review')) ?></button>
  </div>
</form>
<?php endif ?>
