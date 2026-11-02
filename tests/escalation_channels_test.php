<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Clock, Contacts, Email, Engine, Messages, Notice, Notifier, Outbox, Store, Transport};
require __DIR__ . '/lib.php';

final class MailX implements Transport {
    public array $sent = []; public $failWhen = null;
    public function send(array $smtp, array $to, string $fromName, string $replyTo, Email $email): void {
        if ($this->failWhen !== null && ($this->failWhen)($to)) { throw new \RuntimeException('smtp down'); }
        $this->sent[] = ['to' => $to, 'subject' => $email->subject];
    }
}
final class PingX implements Notifier {
    public array $got = []; public bool $failAll = false;
    public function notify(array $channel, Notice $notice, string $event): void {
        if ($this->failAll) { throw new \RuntimeException('channel down'); }
        $this->got[] = [$channel['id'], $notice->title, $notice->body, $notice->text(), $event];
    }
}
function ch(string $id, string $url): array { return ['id' => $id, 'type' => 'apprise', 'url' => $url, 'label' => $id, 'secret' => null]; }
function silentWorld(): array {
    $dir = sys_get_temp_dir() . '/dps-esc-' . bin2hex(random_bytes(5)); mkdir($dir, 0700);
    Clock::set((new \DateTimeImmutable('2026-10-07 13:00', new \DateTimeZone('Australia/Sydney')))->getTimestamp());
    $store = new Store($dir); $mail = new MailX(); $ping = new PingX();
    $engine = new Engine($store, new Outbox($store, $mail, $ping), fn (): array => ['result' => 'protected', 'detail' => 't', 'checked_at' => Clock::now()]);
    $store->write('config', ['owner_name' => 'Sam Rivers', 'owner_email' => 'owner@example.test', 'backup_email' => 'owner@backup.test',
        'timezone' => 'Australia/Sydney', 'slot_dow' => 1, 'slot_hour' => 9, 'base_url' => 'https://switch.example.test',
        'smtp' => ['from_email' => 'switch@example.test'], 'installed_at' => Clock::now(),
        'channels' => [ch('ch_owner', 'tgram://OWNERSECRET/1')]]);
    foreach ([['nok', 'Kim', 'kin@example.test'], ['technical', 'Tao', 'tech@example.test'], ['legal', 'Lee', 'legal@example.test']] as [$k, $n, $e]) {
        $c = Contacts::blank($k); $c['name'] = $n; $c['email'] = $e; $c['status'] = 'confirmed';
        $c['confirmed_at'] = $c['reconfirmed_at'] = $c['has_needs_at'] = Clock::now();
        $c['message']['body'] = preg_replace(Messages::MARKER, 'the blue folder', $c['message']['body']);
        $c['channels'] = [ch('ch_' . $k, 'ntfy://SECRET' . $k . '/topic')];
        $engine->contacts()->save($c);
    }
    $engine->tick();
    $errors = $engine->arm();
    if ($errors) { throw new \RuntimeException(implode('; ', $errors)); }
    return [$store, $mail, $ping, $engine];
}
function hours(Engine $e, int $n): void { for ($i = 0; $i < $n; $i++) { Clock::advance(3600); $e->tick(); } }

test('a silent week with working email: contacts get fired notices on channels too', function (): void {
    [$store, , $ping, $engine] = silentWorld();
    hours($engine, 24 * 14);
    same('fired', $engine->state()['cycle']['stage'] ?? '', 'fired');
    $ids = array_unique(array_column($ping->got, 0));
    foreach (['ch_nok', 'ch_technical', 'ch_legal', 'ch_owner'] as $id) { check(in_array($id, $ids, true), "$id notified"); }
    foreach ($ping->got as $g) { check(!str_contains($g[3], 'blue folder'), 'no message prose in notice'); }
    check(!str_contains((string) json_encode($store->history()), 'SECRET'), 'no token in history');
    check(!str_contains((string) json_encode($store->read('mail')), 'SECRET'), 'no token in mail state');
});
test('NOK email fails, channel works: NOK counts as asked and the wait starts', function (): void {
    [, $mail, $ping, $engine] = silentWorld();
    $mail->failWhen = fn (array $to): bool => in_array('kin@example.test', $to, true);
    hours($engine, 24 * 9);
    $cycle = $engine->state()['cycle'];
    check($cycle !== null && $cycle['verify'] !== null, 'verify stage reached');
    check($cycle['verify']['reached'] === true, 'reached via channel');
    check(in_array('ch_nok', array_column($ping->got, 0), true), 'NOK asked on channel');
});
test('a failing channel never blocks email delivery', function (): void {
    [, $mail, $ping, $engine] = silentWorld();
    $ping->failAll = true;
    hours($engine, 24 * 14);
    same('fired', $engine->state()['cycle']['stage'] ?? '', 'fired');
    foreach (['kin@example.test', 'tech@example.test', 'legal@example.test'] as $a) {
        check(count(array_filter($mail->sent, fn ($m) => in_array($a, $m['to'], true))) >= 1, "$a emailed");
    }
    same(3, count($engine->state()['cycle']['notified']), 'all notified');
});
test('fired: email down but channel up is NOT counted as notified, and the channel is told once', function (): void {
    [, $mail, $ping, $engine] = silentWorld();
    $mail->failWhen = fn (array $to): bool => in_array('legal@example.test', $to, true);
    hours($engine, 24 * 14);
    $cycle = $engine->state()['cycle'];
    same('fired', $cycle['stage'], 'fired');
    same(2, count($cycle['notified']), 'legal contact not marked notified');
    same(1, count($cycle['channel_told'] ?? []), 'channel_told recorded');
    $n = count(array_filter($ping->got, fn ($g) => $g[0] === 'ch_legal' && str_contains($g[2], 'could not be sent')));
    same(1, $n, 'channel notice sent once, not every tick');
    $mail->failWhen = null;
    hours($engine, 2);
    same(3, count($engine->state()['cycle']['notified']), 'delivered once email works');
});
test('channel time budget: slow dead channels are skipped, email still sent', function (): void {
    $dir = sys_get_temp_dir() . '/dps-bud-' . bin2hex(random_bytes(5)); mkdir($dir, 0700);
    $store = new Store($dir); $mail = new MailX();
    $slow = new class implements Notifier { public int $calls = 0; public function notify(array $c, Notice $n, string $e): void { $this->calls++; throw new \RuntimeException('down'); } };
    $store->write('config', ['owner_name' => 'S', 'owner_email' => 'o@x.test', 'smtp' => ['from_email' => 's@x.test'],
        'channels' => [ch('a', 'tgram://1'), ch('b', 'tgram://2')]]);
    $outbox = new Outbox($store, $mail, $slow); $outbox->channelBudget = 0.0;
    check($outbox->send('o@x.test', new Email('Hi'), 'owner'), 'email ok');
    same(0, $slow->calls, 'budget of zero skips channels');
    same(1, count($mail->sent), 'email sent');
});
finish();
