<?php
declare(strict_types=1);
namespace DPS\Tests;

// The data folder and the session must exist before the first line of output.
$dir = sys_get_temp_dir() . '/dps-vpage-' . bin2hex(random_bytes(5));
mkdir($dir, 0700);
putenv('DPS_DATA=' . $dir);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_GET['p'] = 'vault';
require __DIR__ . '/lib.php';

use DPS\Web;
use function DPS\store;

if (!defined('DPS_ROOT')) {
    define('DPS_ROOT', dirname(__DIR__));
}

$hash = password_hash('pw-for-tests', PASSWORD_DEFAULT);
store()->write('config', [
    'installed_at' => 1, 'password_hash' => $hash, 'owner_name' => 'Ann "<b>O\'Neil</b>"',
    'owner_email' => 'ann@example.test', 'base_url' => 'https://dps.example.test',
]);
store()->write('contacts', ['contacts' => [
    ['id' => 'c_1', 'kind' => 'nok', 'name' => 'Bob <i>"B"</i>', 'email' => 'secret-bob@example.test', 'phone' => '+48123456789',
        'role_label' => 'Next of kin', 'status' => 'confirmed', 'created_at' => 1],
    ['id' => 'c_2', 'kind' => 'other', 'name' => 'Cy', 'email' => 'cy@example.test', 'phone' => '',
        'role_label' => 'Other', 'status' => 'pending', 'created_at' => 2],
]]);
Web::session();
$_SESSION['owner'] = true;
$_SESSION['pw'] = substr($hash, -16);

function render(): string
{
    ob_start();
    try {
        (static function (): void {
            require dirname(__DIR__) . '/app/pages/vault.php';
        })();
    } finally {
        $html = (string) ob_get_clean();
    }
    return $html;
}

test('the mount carries the data attributes and the module script', function (): void {
    $html = render();
    check(str_contains($html, '<div id="vault-owner"'), 'mount');
    check(str_contains($html, 'data-api="vault-api.php"'), 'api');
    check(str_contains($html, 'data-csrf="' . Web::csrf() . '"'), 'csrf');
    check(str_contains($html, 'data-vault-url="https://dps.example.test/vault.php"'), 'vault url');
    check(preg_match('~<script type="module" src="assets/vault/owner\.js\?v=[^"]+"></script>~', $html) === 1, 'module script');
    check(str_contains($html, '<noscript>'), 'noscript');
    same('Vault: Death Plan Switch', Web::$title, 'title');
});
test('data attributes are escaped, and contacts are id and name only', function (): void {
    $html = render();
    check(!str_contains($html, '<b>O') && !str_contains($html, '<i>'), 'no raw tags');
    check(!str_contains($html, "O'Neil"), 'quote escaped in owner name');
    preg_match('~data-contacts="([^"]*)"~', $html, $m);
    check(isset($m[1]), 'contacts attribute present');
    $list = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
    same([['id' => 'c_1', 'name' => 'Bob <i>"B"</i>'], ['id' => 'c_2', 'name' => 'Cy']], $list, 'contacts list');
    check(!str_contains($html, 'secret-bob@example.test') && !str_contains($html, '+48123456789') && !str_contains($html, 'cy@example.test'), 'no emails or phones');
});
test('the nav lists Vault after Contacts', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__) . '/app/pages/_layout.php');
    check(preg_match("~'contacts' => t\\('Contacts'\\), 'vault' => t\\('Vault'\\)~", $source) === 1, 'nav order');
});
finish();
