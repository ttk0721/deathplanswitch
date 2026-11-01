<?php
/**
 * Death Plan Switch: the owner's pages.
 *
 *   index.php              the dashboard
 *   index.php?p=contacts   and so on
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use DPS\Web;
use function DPS\store;

$page = Web::get('p');

if ($page === 'logout') {
    Web::signOut();
    Web::redirect('index.php?p=login');
}

// Visitors who are not signed in see the public page, once the switch is installed.
if ($page === '' && !empty(store()->read('config')['installed_at']) && !Web::signedIn()) {
    Web::$layout = 'plain';
    Web::page('public');
}

$pages = ['switch', 'login', 'contacts', 'contact', 'review', 'settings', 'history', 'vault', 'nodes', 'public'];
Web::page(in_array($page, $pages, true) ? $page : 'switch');
