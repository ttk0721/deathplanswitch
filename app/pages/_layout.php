<?php
/**
 * The page wrapper. Expects $content (HTML) from Web::page().
 * To show your own logo, save it as assets/logo.svg or assets/logo.png.
 */
declare(strict_types=1);

namespace DPS;

if (!isset($content)) {
    exit;
}

$admin = Web::$layout === 'admin';
$current = Web::get('p', 'switch');
$nav = ['switch' => 'Switch', 'contacts' => 'Contacts', 'vault' => 'Vault', 'nodes' => 'Nodes', 'settings' => 'Settings', 'history' => 'History'];
$navText = ['switch' => t('Switch'), 'contacts' => t('Contacts'), 'vault' => t('Vault'), 'nodes' => t('Nodes'), 'settings' => t('Settings'), 'history' => t('History')];
$navFor = ['contact' => 'contacts', 'review' => 'contacts', 'login' => ''];
$current = $navFor[$current] ?? $current;

$logo = null;
foreach (['logo.svg', 'logo.png'] as $file) {
    if (is_file(DPS_ROOT . '/assets/' . $file)) {
        $logo = 'assets/' . $file;
        break;
    }
}

// Faults that stop the switch working are shown on every signed-in page.
$faults = [];
if ($admin) {
    try {
        $faults = array_values(array_filter(engine()->warnings(), static fn (array $w): bool => $w['level'] === 'bad'));
    } catch (\Throwable) {
        $faults = [];
    }
}

// A secondary node mirrors the primary; say so on every admin page.
$mirrors = null;
if ($admin) {
    try {
        $self = Node::publicIdentity(store());
        if ($self !== null && Cluster::enabled(store()) && !Cluster::isPrimary(store(), $self['id'])) {
            $mirrors = (string) (Cluster::find(store(), (string) Cluster::read(store())['primary'])['name'] ?? t('the primary'));
        }
    } catch (\Throwable) {
        $mirrors = null;
    }
}
?>
<!doctype html>
<html lang="<?= e(lang()) ?>" dir="<?= e(I18n::dir()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(Web::$title) ?></title>
<link rel="stylesheet" href="assets/app.css?v=<?= e(VERSION) ?>">
<link rel="icon" href="data:,">
</head>
<body class="layout-<?= $admin ? 'admin' : 'plain' ?>">
<?php if ($admin): ?>
<header class="masthead">
  <a class="wordmark" href="index.php"><?php if ($logo): ?><img src="<?= e($logo) ?>" alt="Death Plan Switch"><?php else: ?>Death Plan Switch<?php endif ?></a>
  <nav aria-label="Main">
<?php foreach ($nav as $key => $label): ?>
    <a href="index.php<?= $key === 'switch' ? '' : '?p=' . $key ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>><?= e($navText[$key]) ?></a>
<?php endforeach ?>
    <a href="index.php?p=logout"><?= e(t('Sign out')) ?></a>
  </nav>
</header>
<?php endif ?>
<main>
<?php foreach (Web::flashes() as [$type, $message]): ?>
  <div class="notice notice-<?= e($type) ?>" role="status"><p><?= e($message) ?></p></div>
<?php endforeach ?>
<?php if ($mirrors !== null): ?>
  <div class="notice notice-warn" role="status"><p><?= e(t('This node mirrors {name}. Change settings there; check-ins here count everywhere.', ['name' => $mirrors])) ?></p></div>
<?php endif ?>
<?php if ($faults): ?>
  <div class="notice notice-bad" role="alert">
    <ul>
<?php foreach ($faults as $fault): ?>
      <li><?= e($fault['text']) ?></li>
<?php endforeach ?>
    </ul>
  </div>
<?php endif ?>
<?php if ($admin): ?>
<?= $content ?>
<?php else: ?>
  <div class="sheet">
<?= $content ?>
  </div>
<?php endif ?>
</main>
<footer class="colophon">
  <?= $admin ? e(t('Death Plan Switch {version}. Free software under the MIT licence.', ['version' => VERSION])) : e(t('Death Plan Switch')) ?>
</footer>
<script src="assets/app.js?v=<?= e(VERSION) ?>" defer></script>
</body>
</html>
