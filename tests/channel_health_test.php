<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Clock, Email, Engine, Outbox, Store, Transport};
require __DIR__ . '/lib.php';

final class Throwing implements Transport
{
    public function send(array $smtp, array $to, string $fromName, string $replyTo, Email $email): void
    {
        throw new \RuntimeException('no mail');
    }
}

function engineWith(array $channels): Engine
{
    $dir = sys_get_temp_dir() . '/dps-ch-' . bin2hex(random_bytes(6));
    mkdir($dir, 0700, true);
    Clock::set(1_000_000);
    $store = new Store($dir);
    $store->write('config', ['owner_email' => 'o@x.test', 'timezone' => 'UTC', 'base_url' => 'https://s.test', 'installed_at' => 1]);
    $store->write('mail', ['channels' => $channels]);
    return new Engine($store, new Outbox($store, new Throwing()), fn (): array => ['result' => 'protected', 'detail' => '', 'checked_at' => 0]);
}
function channelWarnings(Engine $e): array
{
    return array_values(array_filter($e->warnings(), fn (array $w): bool => $w['key'] === 'channels'));
}

test('a channel whose latest result is an error warns', function (): void {
    $e = engineWith(['ch_k' => ['label' => 'ntfy', 'ok_at' => 100, 'error' => 'down', 'error_at' => 200]]);
    $w = channelWarnings($e);
    same(1, count($w), 'one warning');
    same('bad', $w[0]['level'], 'level');
    check(str_contains($w[0]['text'], 'ntfy') && str_contains($w[0]['text'], 'down'), 'text: ' . $w[0]['text']);
    check(in_array('channels', $e->health()['problems'], true), 'health problem');
});
test('a later success clears the warning', function (): void {
    $e = engineWith(['ch_k' => ['label' => 'ntfy', 'ok_at' => 300, 'error' => 'down', 'error_at' => 200]]);
    same([], channelWarnings($e), 'none');
});
test('a channel that only ever worked does not warn', function (): void {
    $e = engineWith(['ch_k' => ['label' => 'ntfy', 'ok_at' => 100]]);
    same([], channelWarnings($e), 'none');
});
test('label and error stay plain text', function (): void {
    $e = engineWith(['ch_k' => ['label' => '<b>x</b>', 'error' => '<i>&</i>', 'error_at' => 200]]);
    $w = channelWarnings($e);
    check(str_contains($w[0]['text'], '<b>x</b>') && str_contains($w[0]['text'], '<i>&</i>'), 'not escaped here: ' . $w[0]['text']);
});
finish();
