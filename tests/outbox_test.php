<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Clock, Email, Notice, Notifier, Outbox, Store, Transport};
require __DIR__ . '/lib.php';

final class Mail implements Transport {
    public bool $fail = false;
    public function send(array $smtp, array $to, string $fromName, string $replyTo, Email $email): void { if ($this->fail) { throw new \RuntimeException('smtp down'); } }
}
final class Pings implements Notifier {
    public array $got = []; public array $failIds = [];
    public function notify(array $channel, Notice $notice, string $event): void {
        if (in_array($channel['id'], $this->failIds, true)) { throw new \RuntimeException('channel down'); }
        $this->got[] = [$channel['id'], $notice->title, $event];
    }
}
function world(): array {
    $dir = sys_get_temp_dir() . '/dps-outbox-' . bin2hex(random_bytes(5)); mkdir($dir, 0700);
    Clock::set(1_800_000_000);
    $store = new Store($dir);
    $store->write('config', ['owner_name' => 'Sam', 'owner_email' => 'o@x.test', 'smtp' => ['from_email' => 's@x.test'],
        'channels' => [['id' => 'ch_o', 'type' => 'apprise', 'url' => 'tgram://o', 'label' => 'tg', 'secret' => null]]]);
    $store->write('contacts', ['contacts' => [['id' => 'c_1', 'kind' => 'nok', 'email' => 'k@x.test', 'phone' => '', 'status' => 'confirmed',
        'channels' => [['id' => 'ch_k', 'type' => 'apprise', 'url' => 'ntfy://k', 'label' => 'ntfy', 'secret' => null]]]]]);
    $mail = new Mail(); $pings = new Pings();
    return [$store, $mail, $pings, new Outbox($store, $mail, $pings)];
}
test('owner email also goes to the owner channels', function (): void {
    [, , $pings, $outbox] = world();
    check($outbox->send('o@x.test', new Email('Hello')), 'sent');
    same([['ch_o', 'Hello', 'owner']], $pings->got, 'owner only');
});
test('contact email goes to that contact only', function (): void {
    [, , $pings, $outbox] = world();
    $outbox->send('k@x.test', new Email('Is all well?'), 'contact');
    same([['ch_k', 'Is all well?', 'contact']], $pings->got, 'kin');
});
test('email down, channel up: delivered, and the email failure is kept', function (): void {
    [$store, $mail, , $outbox] = world(); $mail->fail = true;
    check($outbox->send('k@x.test', new Email('Is all well?'), 'contact'), 'delivered');
    check(!empty($store->read('mail')['last_send_error']), 'email error kept');
    same(1_800_000_000, $store->read('mail')['channels']['ch_k']['ok_at'], 'ok_at');
});
test('everything down: not delivered, each failure recorded', function (): void {
    [$store, $mail, $pings, $outbox] = world(); $mail->fail = true; $pings->failIds = ['ch_k'];
    check(!$outbox->send('k@x.test', new Email('Is all well?'), 'contact'), 'not delivered');
    $ch = $store->read('mail')['channels']['ch_k'];
    same(['ntfy', 'channel down', 1_800_000_000], [$ch['label'], $ch['error'], $ch['error_at']], 'record');
    $events = array_column($store->history(), 'event');
    check(in_array('channel.failed', $events, true), 'history');
});
test('codes and SMTP tests never go to channels', function (): void {
    [, , $pings, $outbox] = world();
    $outbox->send('o@x.test', new Email('Your code'), 'owner', null, false);
    $outbox->send('o@x.test', new Email('SMTP test'), 'owner', ['from_email' => 's@x.test']);
    same([], $pings->got, 'none');
});
test('history never holds a full channel address', function (): void {
    [$store, , , $outbox] = world();
    $outbox->send('k@x.test', new Email('Hi'), 'contact');
    check(!str_contains((string) file_get_contents($store->dir() . '/history.jsonl'), 'ntfy://k'), 'masked');
});
test('without a notifier, email only', function (): void {
    [$store, $mail] = world();
    check((new Outbox($store, $mail))->send('o@x.test', new Email('Hi')), 'sent');
});
finish();
