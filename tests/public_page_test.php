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
function setState(?array $cycle, bool $armed = true): void
{
    $s = \DPS\store()->read('state');
    $s['armed'] = $armed;
    $s['cycle'] = $cycle;
    \DPS\store()->write('state', $s);
}
function setFired(bool $fired): void
{
    $s = \DPS\store()->read('state');
    $s['cycle'] = $fired ? ['stage' => 'fired'] : null;
    \DPS\store()->write('state', $s);
}

function install(): void
{
    $c = \DPS\store()->read('config');
    $c['installed_at'] = 1700000000;
    $c['owner_name'] = 'Ann';
    \DPS\store()->write('config', $c);
    \DPS\I18n::setLanguage('en');
}

test('before the install the page says the switch is being set up and offers the installer', function (): void {
    \DPS\I18n::setLanguage('pl');
    $html = render();
    check(str_contains($html, 'Ten przełącznik jest w trakcie konfiguracji.'), 'setup wording');
    check(str_contains($html, 'href="install.php"'), 'set up button');
    check(str_contains($html, 'index.php?p=login'), 'sign-in button');
    check(!str_contains($html, 'Wszystko w porządku'), 'no all-well claim before setup');
});
test('not armed: not active, all is well, with the sign-in button', function (): void {
    install();
    setState(null, false);
    $html = render();
    check(str_contains($html, 'This switch is not active. All is well.'), 'not active wording');
    check(preg_match('~<a class="button landing-signin" href="index.php\?p=login">Sign in</a>~', $html) === 1, 'sign-in button');
    check(!str_contains($html, 'Owner sign-in'), 'old bottom link gone');
    check(!str_contains($html, 'triggered'), 'not triggered');
    check(str_contains($html, 'assets/landing/dawn.svg'), 'default background');
});
test('armed and normal says all is well', function (): void {
    setState(null);
    check(str_contains(render(), 'This switch is armed. All is well.'), 'armed, normal');
    setState(['stage' => 'checkin', 'reminders' => []]);
    check(str_contains(render(), 'All is well.'), 'a fresh weekly check-in is a normal week');
});
test('overdue and verify are never shown to the public', function (): void {
    foreach ([['stage' => 'checkin', 'reminders' => [1700000000]], ['stage' => 'verify', 'reminders' => [1, 2]]] as $cycle) {
        setState($cycle);
        $html = render();
        check(str_contains($html, 'This switch is armed. All is well.'), 'same calm wording');
        foreach (['overdue', 'verify', 'late', 'check-in', 'reminder', 'next of kin', 'pilot-overdue', 'pilot-verify'] as $word) {
            check(stripos($html, $word) === false, "leaks '$word'");
        }
    }
});
test('while armed and not fired the page is byte-identical in every stage', function (): void {
    setState(null);
    $quiet = render();
    check(str_contains($quiet, 'This switch is armed. All is well.'), 'wording');
    foreach ([
        ['stage' => 'checkin', 'reminders' => []],
        ['stage' => 'checkin', 'reminders' => [1700000000]],
        ['stage' => 'checkin', 'reminders' => [1700000000, 1700086400]],
        ['stage' => 'verify', 'reminders' => []],
        ['stage' => 'verify', 'reminders' => [1, 2]],
    ] as $cycle) {
        setState($cycle);
        same($quiet, render(), 'stage ' . $cycle['stage'] . ' with ' . count($cycle['reminders']) . ' reminders');
    }
    \DPS\I18n::setLanguage('pl');
    setState(['stage' => 'checkin', 'reminders' => []]);
    $pl = render();
    check(str_contains($pl, 'Przełącznik czuwa. Wszystko w porządku.'), 'Polish wording');
    setState(['stage' => 'verify', 'reminders' => [1]]);
    same($pl, render(), 'Polish, verify');
    \DPS\I18n::setLanguage('en');
});
test('the quote, its attribution and the status come in that order, then the paragraphs', function (): void {
    setState(null);
    $c = \DPS\store()->read('config');
    $c['landing'] = ['title' => 'Hi', 'text' => "<script>alert(1)</script>\n\nSecond", 'quote' => 'Be <b>kind</b>.', 'by' => 'Ann B.', 'background' => 'sea'];
    \DPS\store()->write('config', $c);
    $html = render();
    check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'escaped');
    check(str_contains($html, 'Be &lt;b&gt;kind&lt;/b&gt;.') && str_contains($html, '— Ann B.'), 'quote and attribution escaped');
    check(str_contains($html, '<p>Second</p>'), 'paragraphs');
    $at = static fn (string $needle): int => (int) strpos($html, $needle);
    check($at('landing-quote') < $at('landing-by') && $at('landing-by') < $at('landing-state') && $at('landing-state') < $at('&lt;script&gt;'), 'order');
    check(str_contains($html, '<h1 class="landing-name">Hi</h1>'), 'title');
    check(str_contains($html, 'assets/landing/sea.svg'), 'chosen background');
});
test('without a quote the title is the large line; attribution falls back to the owner name', function (): void {
    $c = \DPS\store()->read('config');
    $c['landing'] = ['title' => 'My switch'];
    \DPS\store()->write('config', $c);
    $html = render();
    check(str_contains($html, 'landing-quote-plain') && !str_contains($html, 'landing-by'), 'title as the large line');
    $c['landing'] = ['quote' => 'Hello'];
    \DPS\store()->write('config', $c);
    check(str_contains(render(), '— Ann'), 'owner name under the quote');
});
test('an own image that is missing falls back to the default background', function (): void {
    $c = \DPS\store()->read('config');
    $c['landing'] = ['background' => 'own', 'image' => 'background-0123456789abcdef.png'];
    \DPS\store()->write('config', $c);
    $html = render();
    check(str_contains($html, 'assets/landing/dawn.svg') && !str_contains($html, 'landing-bg.php'), 'fallback');
});
test('after release it says triggered and links the vault only with recipients', function (): void {
    $c = \DPS\store()->read('config');
    unset($c['landing']);
    \DPS\store()->write('config', $c);
    setFired(true);
    $html = render();
    check(str_contains($html, 'This switch has been triggered.'), 'triggered');
    check(!str_contains($html, 'vault.php'), 'no recipients, no link');
    $v = \DPS\store()->read('vault', ['version' => 1, 'owner' => null, 'recipients' => [], 'packages' => []]);
    $v['recipients'][] = ['id' => 'r_0123456789abcdef'];
    \DPS\store()->write('vault', $v);
    check(str_contains(render(), 'vault.php'), 'link');
});
finish();
