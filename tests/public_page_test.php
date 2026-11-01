<?php
declare(strict_types=1);
namespace DPS\Tests;
$dir = sys_get_temp_dir() . '/dps-public-' . bin2hex(random_bytes(5));
mkdir($dir, 0700);
putenv('DPS_DATA=' . $dir);
require __DIR__ . '/lib.php';
if (!defined('DPS_ROOT')) {
    define('DPS_ROOT', dirname(__DIR__));
}

function render(): string
{
    ob_start();
    try {
        (static function (): void { require DPS_ROOT . '/app/pages/public.php'; })();
    } finally {
        $html = (string) ob_get_clean();
    }
    return $html;
}
function setFired(bool $fired): void
{
    $s = \DPS\store()->read('state');
    $s['cycle'] = $fired ? ['stage' => 'fired'] : null;
    \DPS\store()->write('state', $s);
}

test('before release the defaults show, with the owner sign-in link', function (): void {
    $html = render();
    check(str_contains($html, 'Death Plan Switch') && str_contains($html, 'This switch is not active.'), 'defaults');
    check(str_contains($html, 'index.php?p=login'), 'sign-in link');
    check(!str_contains($html, 'triggered'), 'not triggered');
});
test('the owner text is escaped and split into paragraphs', function (): void {
    $c = \DPS\store()->read('config');
    $c['landing'] = ['title' => 'Hi', 'text' => "<script>alert(1)</script>\n\nSecond"];
    \DPS\store()->write('config', $c);
    $html = render();
    check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'escaped');
    check(str_contains($html, '<p>Second</p>'), 'paragraphs');
});
test('after release it says triggered and links the vault only with recipients', function (): void {
    setFired(true);
    $html = render();
    check(str_contains($html, 'triggered'), 'triggered');
    check(!str_contains($html, 'vault.php'), 'no recipients, no link');
    $v = \DPS\store()->read('vault', ['version' => 1, 'owner' => null, 'recipients' => [], 'packages' => []]);
    $v['recipients'][] = ['id' => 'r_0123456789abcdef'];
    \DPS\store()->write('vault', $v);
    check(str_contains(render(), 'vault.php'), 'link');
});
finish();
