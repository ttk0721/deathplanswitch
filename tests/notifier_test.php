<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{HttpNotifier, Notice};
require __DIR__ . '/lib.php';

$dir = sys_get_temp_dir() . '/dps-capture-' . bin2hex(random_bytes(4));
mkdir($dir);
$port = 18500 + random_int(0, 400);
$null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", __DIR__ . '/fixtures/capture.php'],
    [1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes, null, ['CAPTURE_DIR' => $dir] + getenv());
register_shutdown_function(static fn () => proc_terminate($proc));
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) { usleep(100000); }
$last = static function () use ($dir): array {
    $files = glob($dir . '/*.json'); sort($files);
    return json_decode((string) file_get_contents((string) end($files)), true);
};
$notice = new Notice('Title', 'Body', 'https://s.test/l');

test('Apprise gets the address, title and text', function () use ($port, $last, $notice): void {
    (new HttpNotifier("http://127.0.0.1:$port/api/"))->notify(['id' => 'ch_1', 'type' => 'apprise', 'url' => 'discord://1/2', 'label' => 'd', 'secret' => null], $notice, 'owner');
    $req = $last();
    same('/api/notify/', $req['uri'], 'uri');
    same(['urls' => 'discord://1/2', 'title' => 'Title', 'body' => "Body\nhttps://s.test/l", 'type' => 'info'], json_decode($req['body'], true), 'payload');
});
test('an Apprise error throws with the status', function () use ($port, $notice): void {
    try {
        (new HttpNotifier("http://127.0.0.1:$port/fail"))->notify(['id' => 'ch_1', 'type' => 'apprise', 'url' => 'discord://1/2', 'label' => 'd', 'secret' => null], $notice, 'owner');
        check(false, 'should throw');
    } catch (\RuntimeException $e) { check(str_contains($e->getMessage(), '500'), 'status in message'); }
});
test('no Apprise address means a clear error', function () use ($notice): void {
    try { (new HttpNotifier(''))->notify(['id' => 'x', 'type' => 'apprise', 'url' => 'ntfy://t', 'label' => 'n', 'secret' => null], $notice, 'owner'); check(false, 'should throw'); }
    catch (\RuntimeException $e) { same('Apprise is not set up', $e->getMessage(), 'message'); }
});
test('a webhook gets signed JSON', function () use ($port, $last, $notice): void {
    (new HttpNotifier(''))->notify(['id' => 'ch_2', 'type' => 'webhook', 'url' => "http://127.0.0.1:$port/hook", 'label' => 'h', 'secret' => 'k'], $notice, 'contact');
    $req = $last();
    $body = json_decode($req['body'], true);
    same(['event', 'title', 'body', 'link', 'sent_at'], array_keys($body), 'keys');
    same('contact', $req['headers']['X-Dps-Event'] ?? $req['headers']['X-DPS-Event'] ?? null, 'event header');
    same(HttpNotifier::signature($req['body'], 'k'), $req['headers']['X-Dps-Signature'] ?? $req['headers']['X-DPS-Signature'] ?? null, 'signature');
});
test('an unreachable webhook throws', function () use ($notice): void {
    try { (new HttpNotifier(''))->notify(['id' => 'x', 'type' => 'webhook', 'url' => 'http://127.0.0.1:1/x', 'label' => 'h', 'secret' => 'k'], $notice, 'owner'); check(false, 'should throw'); }
    catch (\RuntimeException) { check(true, 'threw'); }
});
test('signature is sha256 HMAC', function (): void {
    same('sha256=' . hash_hmac('sha256', '{}', 'k'), HttpNotifier::signature('{}', 'k'), 'hmac');
});
finish();
