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
$landing = Web::$layout === 'landing';
$install = Web::$layout === 'install';
$current = Web::get('p', 'switch');
$navText = ['switch' => t('Switch'), 'contacts' => t('Contacts'), 'vault' => t('Vault'), 'nodes' => t('Nodes'), 'settings' => t('Settings'), 'history' => t('History')];
$navFor = ['contact' => 'contacts', 'review' => 'contacts', 'login' => ''];
$current = $navFor[$current] ?? $current;

// Line icons for the menus, 24x24, drawn with the text colour.
$icons = [
    'switch' => '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.8 0"/>',
    'contacts' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
    'vault' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'settings' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
    'nodes' => '<rect x="9" y="2" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="16" y="16" width="6" height="6" rx="1"/><path d="M12 8v4"/><path d="M5 16v-2a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2"/>',
    'history' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
    'more' => '<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>',
];
$icon = static fn (string $name): string => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $icons[$name] . '</svg>';
$tabs = ['switch', 'contacts', 'vault', 'settings'];
$more = ['nodes', 'history'];
$href = static fn (string $key): string => 'index.php' . ($key === 'switch' ? '' : '?p=' . $key);

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

// The state of the switch, as one light and one word, for the menus.
$mode = null;
$user = ['name' => '', 'line' => ''];
if ($admin) {
    try {
        $mode = engine()->status()['mode'];
        $config = store()->read('config');
        $user['name'] = (string) ($config['owner_name'] ?? '');
        $user['line'] = (string) ($config['owner_email'] ?? '');
        $identity = Node::publicIdentity(store());
        if ($identity !== null && trim((string) ($identity['name'] ?? '')) !== '') {
            $user['line'] = (string) $identity['name'];
        }
    } catch (\Throwable) {
        $mode = null;
    }
}
$modeClass = ['setup' => 'off', 'paused' => 'off', 'armed' => 'armed', 'overdue' => 'overdue', 'verify' => 'verify', 'fired' => 'fired'][$mode ?? ''] ?? 'off';
$modeText = [
    'setup' => t('Not armed'), 'paused' => t('Paused'), 'armed' => t('Armed'),
    'overdue' => t('Check-in overdue'), 'verify' => t('Your next of kin has been asked'), 'fired' => t('The switch has fired'),
][$mode ?? ''] ?? '';
$initials = $user['name'] !== '' ? mb_strtoupper(mb_substr($user['name'], 0, 2)) : 'DPS';

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
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#0c0e11">
<title><?= e(Web::$title) ?></title>
<link rel="stylesheet" href="assets/app.css?v=<?= e(VERSION) ?>">
<link rel="icon" href="data:,">
</head>
<body class="layout-<?= $admin ? 'admin' : ($landing ? 'landing' : ($install ? 'install' : 'plain')) ?>">
<?php if ($admin): ?>
<div class="app">
  <aside class="sidebar">
    <a class="brand" href="index.php">
<?php if ($logo): ?>
      <img src="<?= e($logo) ?>" alt="Death Plan Switch">
<?php else: ?>
      <span class="brand-dot" aria-hidden="true">DPS</span>
      <span class="brand-text">
        <span class="brand-name">Death Plan Switch</span>
<?php if ($modeText !== ''): ?>
        <span class="brand-sub"><span class="dot dot-<?= e($modeClass) ?>" aria-hidden="true"></span><?= e($modeText) ?></span>
<?php endif ?>
      </span>
<?php endif ?>
    </a>
    <nav class="nav-section" aria-label="<?= e(t('Menu')) ?>">
<?php foreach ($navText as $key => $label): ?>
      <a class="nav-item" href="<?= e($href($key)) ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>><span class="nav-ico"><?= $icon($key) ?></span><?= e($label) ?></a>
<?php endforeach ?>
    </nav>
    <div class="user-card">
      <span class="avatar" aria-hidden="true"><?= e($initials) ?></span>
      <span class="user-text">
        <span class="user-name"><?= e($user['name'] !== '' ? $user['name'] : 'Death Plan Switch') ?></span>
        <span class="user-role"><?= e($user['line']) ?></span>
      </span>
      <a class="icon-link" href="index.php?p=logout" title="<?= e(t('Sign out')) ?>" aria-label="<?= e(t('Sign out')) ?>"><?= $icon('logout') ?></a>
    </div>
  </aside>
  <div class="content">
    <header class="mobile-top">
      <a class="brand" href="index.php">
<?php if ($logo): ?>
        <img src="<?= e($logo) ?>" alt="Death Plan Switch">
<?php else: ?>
        <span class="brand-dot" aria-hidden="true">DPS</span>
        <span class="brand-text"><span class="brand-name">Death Plan Switch</span></span>
<?php endif ?>
      </a>
<?php if ($modeText !== ''): ?>
      <span class="mobile-state"><span class="dot dot-<?= e($modeClass) ?>" aria-hidden="true"></span><?= e($modeText) ?></span>
<?php endif ?>
    </header>
<?php endif ?>
<main class="<?= $admin ? 'main' : 'plain-main' ?>">
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
<?php if ($admin || $landing || $install): ?>
<?= $content ?>
<?php else: ?>
  <div class="sheet">
<?= $content ?>
  </div>
<?php endif ?>
</main>
<?php if (!$landing): ?>
<footer class="colophon">
  <?= $admin ? e(t('Death Plan Switch {version}. Free software under the MIT licence.', ['version' => VERSION])) : e(t('Death Plan Switch')) ?>
</footer>
<?php endif ?>
<?php if ($admin): ?>
  </div>
  <nav class="mobile-bar" aria-label="<?= e(t('Menu')) ?>">
<?php foreach ($tabs as $key): ?>
    <a class="tab" href="<?= e($href($key)) ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>><?= $icon($key) ?><span><?= e($navText[$key]) ?></span></a>
<?php endforeach ?>
    <details class="more">
      <summary class="tab"<?= in_array($current, $more, true) ? ' aria-current="page"' : '' ?>><?= $icon('more') ?><span><?= e(t('More')) ?></span></summary>
      <div class="more-sheet">
<?php foreach ($more as $key): ?>
        <a class="nav-item" href="<?= e($href($key)) ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>><span class="nav-ico"><?= $icon($key) ?></span><?= e($navText[$key]) ?></a>
<?php endforeach ?>
        <a class="nav-item" href="index.php?p=logout"><span class="nav-ico"><?= $icon('logout') ?></span><?= e(t('Sign out')) ?></a>
      </div>
    </details>
  </nav>
</div>
<?php endif ?>
<script src="assets/app.js?v=<?= e(VERSION) ?>" defer></script>
</body>
</html>
