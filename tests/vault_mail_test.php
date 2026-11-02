<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Clock, Contacts, Email, Engine, Messages, Outbox, Store, Transport, Vault};
require __DIR__ . '/lib.php';

final class MailV implements Transport {
    public array $sent = [];
    public function send(array $smtp, array $to, string $fromName, string $replyTo, Email $email): void {
        $this->sent[] = ['to' => $to, 'subject' => $email->subject, 'text' => $email->text()];
    }
}
function b(int $n): string { return rtrim(strtr(base64_encode(str_repeat('a', $n)), '+/', '-_'), '='); }
function firedWorld(): array {
    $dir = sys_get_temp_dir() . '/dps-vmail-' . bin2hex(random_bytes(5)); mkdir($dir, 0700);
    Clock::set((new \DateTimeImmutable('2026-10-07 13:00', new \DateTimeZone('Australia/Sydney')))->getTimestamp());
    $store = new Store($dir); $mail = new MailV();
    $engine = new Engine($store, new Outbox($store, $mail), fn (): array => ['result' => 'protected', 'detail' => 't', 'checked_at' => Clock::now()]);
    $store->write('config', ['owner_name' => 'Sam Rivers', 'owner_email' => 'owner@example.test', 'backup_email' => 'owner@backup.test',
        'timezone' => 'Australia/Sydney', 'slot_dow' => 1, 'slot_hour' => 9, 'base_url' => 'https://switch.example.test',
        'smtp' => ['from_email' => 'switch@example.test'], 'installed_at' => Clock::now()]);
    $ids = [];
    foreach ([['nok', 'Kim', 'kin@example.test'], ['technical', 'Tao', 'tech@example.test'], ['legal', 'Lee', 'legal@example.test']] as [$k, $n, $e]) {
        $c = Contacts::blank($k); $c['name'] = $n; $c['email'] = $e; $c['status'] = 'confirmed';
        $c['confirmed_at'] = $c['reconfirmed_at'] = $c['has_needs_at'] = Clock::now();
        $c['message']['body'] = preg_replace(Messages::MARKER, 'the blue folder', $c['message']['body']);
        $engine->contacts()->save($c);
        $ids[$k] = $c['id'];
    }
    (new Vault($store))->saveRecipient([
        'id' => 'r_0123456789abcdef', 'contact_id' => $ids['technical'], 'label' => 'Tao',
        'code' => ['salt' => b(16), 'iv' => b(12), 'ct' => b(48)], 'password' => null,
        'owner_wrapped' => ['iv' => b(12), 'ct' => b(20)],
    ]);
    $engine->tick();
    $errors = $engine->arm();
    if ($errors) { throw new \RuntimeException(implode('; ', $errors)); }
    for ($i = 0; $i < 24 * 14; $i++) { Clock::advance(3600); $engine->tick(); }
    return [$mail, $engine];
}

test('vaultInvite text has the URL and no code', function (): void {
    $url = 'https://switch.example.test/vault.php?invite=TOKEN123';
    $m = Messages::vaultInvite(['owner_name' => 'Sam Rivers'], $url);
    same('Sam Rivers asks you to set a password for their vault', $m->subject, 'subject');
    check(str_contains($m->text(), $url), 'url');
    check(str_contains($m->text(), 'Set your password'), 'link label');
    check(str_contains($m->text(), '30 days'), '30 days');
});
test('fired mail to a vault recipient links to the vault, others do not', function (): void {
    [$mail, $engine] = firedWorld();
    same('fired', $engine->state()['cycle']['stage'] ?? '', 'fired');
    $to = fn (string $a) => array_values(array_filter($mail->sent, fn ($m) => in_array($a, $m['to'], true) && str_contains($m['text'], 'blue folder')));
    $tech = $to('tech@example.test');
    same(1, count($tech), 'tech got the message');
    check(str_contains($tech[0]['text'], 'Open the vault'), 'label');
    check(str_contains($tech[0]['text'], 'https://switch.example.test/vault.php'), 'link');
    foreach (['legal@example.test', 'kin@example.test'] as $a) {
        foreach ($mail->sent as $m) {
            if (in_array($a, $m['to'], true)) { check(!str_contains($m['text'], 'vault.php'), "$a has no vault link"); }
        }
    }
});
finish();
