<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Email, Engine, Outbox, Store, Transport};
require __DIR__ . '/lib.php';

final class NoMail implements Transport {
    public function send(array $smtp, array $to, string $fromName, string $replyTo, Email $email): void { throw new \RuntimeException('no mail in this test'); }
}
function released_engine(?array $cycle): Engine {
    $dir = sys_get_temp_dir() . '/dps-released-' . bin2hex(random_bytes(5)); mkdir($dir, 0700);
    $store = new Store($dir);
    $store->write('state', ['cycle' => $cycle]);
    return new Engine($store, new Outbox($store, new NoMail()), fn (): array => ['result' => 'protected', 'detail' => '', 'checked_at' => 0]);
}
test('released is true only when the cycle has fired', function (): void {
    same(true, released_engine(['stage' => 'fired'])->released(), 'fired');
    same(false, released_engine(['stage' => 'verify'])->released(), 'verify');
    same(false, released_engine(null)->released(), 'no cycle');
});
test('released goes false again once the owner checks in', function (): void {
    $e = released_engine(['stage' => 'fired']);
    same(true, $e->released(), 'fired');
    $e->checkIn('web');
    same(false, $e->released(), 'after check-in');
});
finish();
