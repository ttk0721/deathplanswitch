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

test('signed-in pages carry a sidebar for the desktop and a tab bar for the phone', function (): void {
    $html = render('switch');
    check(str_contains($html, '<aside class="sidebar">'), 'the sidebar');
    check(str_contains($html, '<header class="mobile-top">'), 'the phone top bar');
    check(str_contains($html, 'class="user-card"') && str_contains($html, 'index.php?p=logout'), 'the user block with sign-out');
    preg_match('~<nav class="mobile-bar".*?</nav>~s', $html, $bar);
    $bar = $bar[0] ?? '';
    same(4, preg_match_all('~<a class="tab" href="index\.php(\?p=(contacts|vault|settings))?"~', $bar), 'four main tabs');
    check(str_contains($bar, '<details class="more">') && str_contains($bar, 'p=nodes') && str_contains($bar, 'p=history') && str_contains($bar, 'p=logout'), 'More holds nodes, history and sign-out');
    check(substr_count($html, 'aria-current="page"') >= 1, 'the current page is marked');
});

test('no inline styles and no inline scripts anywhere in the shell', function (): void {
    foreach (['switch', 'contacts', 'settings', 'history', 'nodes', 'vault'] as $page) {
        $html = render($page);
        check($html !== '' && str_contains($html, 'sidebar'), "$page rendered");
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
    foreach (array_unique($m[1]) as $file) {
        check(is_file(ROOT . '/assets/' . $file), "$file exists");
    }
    check(!preg_match('~https?://~', (string) preg_replace('~/\*.*?\*/~s', '', $css)), 'nothing is loaded from another site');
    check(is_file(ROOT . '/assets/fonts/OFL.txt'), 'the licence is shipped');
});
finish();
