<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\Store;
require __DIR__ . '/lib.php';

const ROOT = __DIR__ . '/..';

/** Renders one signed-in page in a child process; returns its HTML. */
function render(string $page, array $query = []): string
{
    $dir = sys_get_temp_dir() . '/dps-shell-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700, true);
    (new Store($dir))->write('config', [
        'installed_at' => 1_700_000_000, 'password_hash' => password_hash('correct horse', PASSWORD_DEFAULT),
        'base_url' => 'http://192.168.1.10:8080', 'timezone' => 'UTC', 'owner_name' => 'Ann Lee', 'owner_email' => 'ann@example.test',
    ]);
    $script = $dir . '/req.php';
    file_put_contents($script, '<?php
        putenv("DPS_DATA=" . getenv("T_DIR"));
        $_SERVER["SCRIPT_NAME"] = "/index.php"; $_SERVER["REQUEST_METHOD"] = "GET";
        $_GET = ["p" => getenv("T_PAGE")] + json_decode(getenv("T_QUERY"), true);
        session_save_path(getenv("T_DIR"));
        require getenv("T_ROOT") . "/app/bootstrap.php";
        DPS\Web::session();
        $cfg = DPS\store()->read("config");
        $_SESSION["owner"] = true; $_SESSION["pw"] = substr($cfg["password_hash"], -16); $_SESSION["csrf"] = "tok";
        DPS\Web::page(getenv("T_PAGE"));
    ');
    $env = ['T_DIR' => $dir, 'T_ROOT' => realpath(ROOT), 'T_PAGE' => $page, 'T_QUERY' => json_encode($query), 'PATH' => getenv('PATH') ?: ''];
    $proc = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    $out = (string) stream_get_contents($pipes[1]);
    proc_close($proc);
    foreach (glob($dir . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($dir);
    return $out;
}

test('signed-in pages carry a top bar and tab strip for the desktop, and a header and bottom bar for the phone', function (): void {
    $html = render('switch');
    check(!str_contains($html, '<aside'), 'no sidebar');
    check(str_contains($html, '<header class="topbar">'), 'the top bar');
    check(!str_contains($html, 'class="user-card"') && str_contains($html, 'index.php?p=logout'), 'sign-out is in the account area');
    preg_match('~<nav class="tabs".*?</nav>~s', $html, $strip);
    $strip = $strip[0] ?? '';
    same(6, preg_match_all('~<a class="tab-link" href="index\.php(\?p=(contacts|vault|nodes|settings|history))?"~', $strip), 'six tabs');
    same(1, substr_count($strip, 'class="tab-indicator"'), 'one sliding underline');
    check(str_contains($html, '<header class="screen-head'), 'the phone screen header');
    preg_match('~<nav class="bottombar".*?</nav>~s', $html, $bar);
    $bar = $bar[0] ?? '';
    same(4, preg_match_all('~<a class="tab" href="index\.php(\?p=(contacts|vault|settings))?"~', $bar), 'four main tabs');
    check(str_contains($bar, '<details class="more">') && str_contains($bar, 'p=nodes') && str_contains($bar, 'p=history') && str_contains($bar, 'p=logout'), 'More holds nodes, history and sign-out');
    same(3, substr_count($bar, 'data-theme-set='), 'More holds the theme choice');
    check(substr_count($html, 'aria-current="page"') >= 1, 'the current page is marked');
});

test('detail screens have an arrow back in the phone header', function (): void {
    $html = render('contact', ['kind' => 'technical']);
    check(str_contains($html, 'class="screen-back" href="index.php?p=contacts"'), 'back to contacts');
    check(!str_contains(render('contacts'), 'class="screen-back"'), 'none on a main screen');
});

test('settings offer the theme choice', function (): void {
    same(6, substr_count(render('settings'), 'data-theme-set='), 'dark, light, system here and in the More sheet');
});

test('no inline styles and no inline scripts anywhere in the shell', function (): void {
    foreach (['switch', 'contacts', 'settings', 'history', 'nodes', 'vault'] as $page) {
        $html = render($page);
        check($html !== '' && str_contains($html, 'class="shell"'), "$page rendered");
        check(!preg_match('~\sstyle=~i', $html), "$page has an inline style");
        check(!preg_match('~<script(?![^>]*\ssrc=)~i', $html), "$page has an inline script");
        check(!preg_match('~<style~i', $html), "$page has a style element");
    }
});

test('the contact page offers a technical helper and never talks about clients', function (): void {
    $html = render('contact', ['kind' => 'technical']);
    check(str_contains($html, 'Add a technical helper'), 'neutral title');
    check(!preg_match('~client|2IC~i', $html), 'no client or 2IC wording');
});

test('the fonts are self-hosted, with their licence', function (): void {
    $css = (string) file_get_contents(ROOT . '/assets/app.css');
    preg_match_all('~url\("(fonts/[^"]+\.woff2)"\)~', $css, $m);
    check(count($m[1]) >= 15, 'font-face rules');
    check(str_contains($css, 'font-family: "Archivo"') && str_contains($css, 'font-family: "IBM Plex Mono"'), 'Archivo and Plex Mono');
    check(!preg_match('~ibm-plex-sans-latin~', $css), 'no unused Plex Sans');
    foreach (array_unique($m[1]) as $file) {
        check(is_file(ROOT . '/assets/' . $file), "$file exists");
    }
    check(!preg_match('~https?://~', (string) preg_replace('~/\*.*?\*/~s', '', $css)), 'nothing is loaded from another site');
    check(is_file(ROOT . '/assets/fonts/OFL.txt') && is_file(ROOT . '/assets/fonts/OFL-Archivo.txt') && is_file(ROOT . '/assets/fonts/OFL-Mono.txt'), 'the licences are shipped');
});

test('the theme is applied before paint by a script from this site, and light is a token set', function (): void {
    $html = render('switch');
    check(preg_match('~<head>.*<script src="assets/theme\.js[^"]*"></script>.*</head>~s', $html) === 1, 'theme.js in the head, not deferred');
    $css = (string) file_get_contents(ROOT . '/assets/app.css');
    check(str_contains($css, ':root[data-motyw="jasny"]'), 'the light theme');
    check(!preg_match('~#(3ddc97|4be5a3|0c0e11)~i', $css), 'no colour from another project');
});
finish();
