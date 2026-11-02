<?php
declare(strict_types=1);
namespace DPS\Tests;
require __DIR__ . '/lib.php';

if (!defined('DPS_ROOT')) {
    define('DPS_ROOT', dirname(__DIR__));
}

function renderChannels(array $channels, ?string $who = null): string
{
    $self = 'index.php?p=settings';
    $channelsWho = $who;
    ob_start();
    try {
        require dirname(__DIR__) . '/app/pages/_channels.php';
    } finally {
        $html = (string) ob_get_clean();
    }
    return $html;
}

test('a webhook secret is shown inside details; the full URL never is', function (): void {
    $hook = ['id' => 'ch_h', 'type' => 'webhook', 'url' => 'https://hooks.example.test/in/SECRETPATH', 'label' => '<b>"x"</b>', 'secret' => 'abc123secret'];
    $apprise = ['id' => 'ch_a', 'type' => 'apprise', 'url' => 'discord://123456/TOKENTOKEN', 'label' => 'discord', 'secret' => 'must-not-show'];
    $html = renderChannels([$hook, $apprise]);
    check(preg_match('~<details class="secret">\s*<summary>Show signing secret</summary>.*abc123secret.*</details>~s', $html) === 1, 'secret in details');
    check(substr_count($html, 'Show signing secret') === 1, 'only the webhook has one');
    check(!str_contains($html, 'must-not-show'), 'apprise has no secret shown');
    check(!str_contains($html, 'SECRETPATH') && !str_contains($html, 'TOKENTOKEN'), 'full url absent');
    check(!str_contains($html, '<b>"x"</b>') && str_contains($html, '&lt;b&gt;'), 'label escaped');
    check(substr_count($html, 'name="csrf"') >= 4, 'csrf on every form');
});
test('the privacy warning names and escapes the person', function (): void {
    $html = renderChannels([], '<i>Ann</i>');
    check(!str_contains($html, '<i>Ann</i>') && str_contains($html, '&lt;i&gt;Ann'), 'escaped');
});
finish();
