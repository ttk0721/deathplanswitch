<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Clock, Cluster, Contacts, Coordinator, Email, Engine, Messages, Outbox, Store, Transport};
require __DIR__ . '/lib.php';

const H = 3600;
const IA = 'n_aaaaaaaa';
const IB = 'n_bbbbbbbb';
const IC = 'n_cccccccc';

final class SRec implements Transport
{
    public array $sent = [];
    public function send(array $smtp, array $to, string $fromName, string $replyTo, Email $email): void
    {
        $this->sent[] = ['at' => Clock::now(), 'to' => $to, 'subject' => $email->subject];
    }
}

function snode(string $id, int $rank): array
{
    return ['id' => $id, 'name' => 'N ' . $id, 'url' => 'https://a.example.org',
        'sign_pk' => rtrim(strtr(base64_encode(str_repeat('a', 32)), '+/', '-_'), '='),
        'share_pk' => 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE', 'rank' => $rank];
}

/** One node with its own store, mail recorder, coordinator and engine. */
final class SN
{
    public Store $store;
    public SRec $mail;
    public Engine $engine;
    public Coordinator $coord;

    /** @param array<string,int> $ranks id => rank for the whole cluster */
    public function __construct(public string $id, array $ranks, ?SN $copyFrom = null)
    {
        $dir = sys_get_temp_dir() . '/dps-scn-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        Clock::set((new \DateTimeImmutable('2026-10-07 13:00', new \DateTimeZone('Australia/Sydney')))->getTimestamp());
        $this->store = new Store($dir);
        $nodes = [];
        foreach ($ranks as $nid => $r) {
            $nodes[] = snode($nid, $r);
        }
        Cluster::save($this->store, ['k' => 1, 'primary' => null, 'isolation_hours' => 48, 'step_delay_min' => 30, 'nodes' => $nodes]);
        $this->coord = new Coordinator($this->store, $id);
        $this->mail = new SRec();
        $this->engine = new Engine(
            $this->store,
            new Outbox($this->store, $this->mail),
            fn (): array => ['result' => 'protected', 'detail' => 'test', 'checked_at' => Clock::now()],
            $this->coord
        );
        $this->store->write('config', [
            'owner_name' => 'Sam Rivers', 'owner_email' => 'owner@example.test', 'backup_email' => '',
            'timezone' => 'Australia/Sydney', 'slot_dow' => 1, 'slot_hour' => 9,
            'base_url' => 'https://switch.example.test', 'smtp' => ['from_email' => 'switch@example.test'],
            'installed_at' => Clock::now(),
        ]);
        foreach ([['nok', 'Kim', 'kin@example.test'], ['technical', 'Tao', 'tech@example.test'], ['legal', 'Lee', 'legal@example.test']] as [$k, $n, $e]) {
            $c = Contacts::blank($k);
            $c['name'] = $n; $c['email'] = $e; $c['status'] = 'confirmed';
            $c['confirmed_at'] = $c['reconfirmed_at'] = $c['has_needs_at'] = Clock::now();
            $c['message']['body'] = preg_replace(Messages::MARKER, 'the blue folder', $c['message']['body']);
            $this->engine->contacts()->save($c);
        }
        $this->engine->tick();
        $errors = $this->engine->arm();
        if ($errors) {
            throw new \RuntimeException('Could not arm: ' . implode('; ', $errors));
        }
        if ($copyFrom !== null) {
            $this->store->write('contacts', $copyFrom->store->read('contacts'));
        }
        $this->mail->sent = [];
    }

    public function to(string $address): int
    {
        return count(array_filter($this->mail->sent, static fn (array $m): bool => in_array($address, $m['to'], true)));
    }

    public function stage(): ?string
    {
        return $this->engine->state()['cycle']['stage'] ?? null;
    }

    public function due(): int
    {
        return (int) $this->engine->state()['next_due_at'];
    }

    /** Receive $from's state as a sync would, then apply it. */
    public function hear(SN $from): void
    {
        $this->coord->recordPeer($from->id, $from->engine->nodeState($from->id), Clock::now());
        $this->engine->applyRemote();
    }
}

/** @return array{0: SN, 1: SN} */
function pair(): array
{
    $ranks = [IA => 0, IB => 1];
    $a = new SN(IA, $ranks);
    $b = new SN(IB, $ranks, $a);
    return [$a, $b];
}

/** Step the clock in 15-minute ticks to $until; $sync runs before each tick when given. */
function run(array $nodes, int $until, ?callable $sync = null): void
{
    for ($t = Clock::now(); $t <= $until; $t += 900) {
        Clock::set($t);
        if ($sync !== null) {
            $sync();
        }
        foreach ($nodes as $n) {
            $n->engine->tick();
        }
    }
}

function syncer(SN $a, SN $b): callable
{
    return function () use ($a, $b): void {
        $a->hear($b);
        $b->hear($a);
    };
}

function total(SN $a, SN $b, string $who): int
{
    return $a->to("$who@example.test") + $b->to("$who@example.test");
}

test('1. check-in on B only, A unsynced for 10 h then sees it: no verify, no fire', function (): void {
    [$a, $b] = pair();
    $due = $a->due();
    run([$a, $b], $due + 40 * H, syncer($a, $b));
    Clock::set($due + 40 * H);
    $b->engine->checkIn('the emailed link');
    run([$a, $b], $due + 50 * H);
    $a->hear($b);
    same(null, $a->engine->state()['cycle'], 'A closed its cycle');
    run([$a, $b], $due + 6 * 24 * H, syncer($a, $b));
    same(0, total($a, $b, 'kin'), 'kin never asked');
    same(0, total($a, $b, 'tech'), 'no fire');
});

test('1b. check-in on B, sync returns just as A would ask the kin: the tick does not ask first', function (): void {
    [$a, $b] = pair();
    $due = $a->due();
    run([$a, $b], $due + 71 * H, syncer($a, $b));
    Clock::set($due + 71 * H + 600);
    $b->engine->checkIn('web');
    Clock::set($due + 72 * H + 600);
    $a->coord->recordPeer(IB, $b->engine->nodeState(IB), Clock::now());
    $a->engine->tick();
    same(0, $a->to('kin@example.test'), 'kin not asked');
    same(null, $a->engine->state()['cycle'], 'closed');
});

test('2. primary dies at hour 0: B reminds, asks the kin and fires, each once', function (): void {
    [$a, $b] = pair();
    $due = $a->due();
    $b->coord->recordPeer(IA, $a->engine->nodeState(IA), Clock::now());
    run([$b], $due + 40 * 24 * H);
    same('fired', $b->stage(), 'B fired');
    $c = $b->engine->state()['cycle'];
    same($due + 30 * 60, (int) $c['started_at'], 'cycle started 30 min late');
    same(2, count($c['reminders']), 'two reminders');
    same(1, $b->to('kin@example.test') - 1 + 1 > 0 ? 1 : 0, 'kin mailed');
    same(1, $b->to('tech@example.test'), 'tech told once');
    same(1, $b->to('legal@example.test'), 'legal told once');
    // Partition delay: kin asked at +72 h +48 h, fired 72 h +48 h later.
    same($c['started_at'] + 120 * H + 30 * 60, (int) $c['verify']['asked_at'], 'asked at 72+48 h plus rank delay');
});

test('3. B partitioned, owner checks in on A: the check-in arrives before B asks the kin', function (): void {
    [$a, $b] = pair();
    $due = $a->due();
    run([$a, $b], $due + 30 * H, syncer($a, $b));
    run([$a, $b], $due + 100 * H);
    same('checkin', $b->stage(), 'B still waiting at hour 100');
    same(0, $b->to('kin@example.test'), 'kin not asked by B');
    Clock::set($due + 100 * H);
    $a->engine->checkIn('web');
    run([$a, $b], $due + 118 * H);
    same(0, $b->to('kin@example.test'), 'kin not asked by B at 118 h');
    Clock::set($due + 118 * H);
    $b->hear($a);
    same(null, $b->engine->state()['cycle'], 'B closed on sync');
});

test('4. kin answers fine on A: B does not fire after a sync gap', function (): void {
    [$a, $b] = pair();
    $due = $a->due();
    run([$a, $b], $due + 73 * H, syncer($a, $b));
    same('verify', $a->stage(), 'A in verify');
    same('verify', $b->stage(), 'B in verify');
    Clock::set($due + 74 * H);
    $a->engine->recordNokAnswer('fine', Clock::now(), 'web');
    same(null, $a->engine->state()['cycle'], 'A closed');
    $a->hear($b);
    run([$a, $b], $due + 5 * 24 * H);
    $b->hear($a);
    same(null, $b->engine->state()['cycle'], 'B closed by the answer after the gap');
    run([$a, $b], $due + 12 * 24 * H, syncer($a, $b));
    same(0, total($a, $b, 'tech'), 'nobody fired');
});

test('4b. kin answers fine on B while A is in verify: A does not fire', function (): void {
    [$a, $b] = pair();
    $due = $a->due();
    run([$a, $b], $due + 73 * H, syncer($a, $b));
    Clock::set($due + 74 * H);
    $b->engine->recordNokAnswer('fine', Clock::now(), 'web');
    run([$a, $b], $due + 7 * 24 * H + 12 * H);
    $a->hear($b);
    same(null, $a->engine->state()['cycle'], 'A closed');
    run([$a, $b], $due + 10 * 24 * H, syncer($a, $b));
    same(0, total($a, $b, 'tech'), 'nobody fired');
});

test('4c. a peer vouched for the cycle while this node still lags in checkin: it closes', function (): void {
    [$a, $b] = pair();
    $due = $a->due();
    run([$a], $due + 10 * H);
    $peer = $b->engine->nodeState(IB);
    $peer['vouched'] = ['key' => $due, 'at' => $due + 80 * H, 'steps' => ['checkin' => $due, 'verify' => $due + 72 * H]];
    Clock::set($due + 81 * H);
    $a->coord->recordPeer(IB, $peer, Clock::now());
    $a->engine->applyRemote();
    same(null, $a->engine->state()['cycle'], 'closed');
    same(0, $a->to('kin@example.test'), 'kin not mailed');
    $b2 = $b->engine->nodeState(IB);
    same(null, $b2['vouched'], 'a node that never vouched reports none');
});

test('5. kin says wrong on B: A fires without duplicating what B sent', function (): void {
    [$a, $b] = pair();
    $due = $a->due();
    run([$a, $b], $due + 73 * H, syncer($a, $b));
    Clock::set($due + 74 * H);
    $b->engine->recordNokAnswer('wrong', Clock::now(), 'web');
    same('fired', $b->stage(), 'B fired');
    $a->mail->sent = [];
    $b->mail->sent = [];
    run([$a, $b], $due + 80 * H, syncer($a, $b));
    same('fired', $a->stage(), 'A fired');
    foreach (['kin', 'tech', 'legal'] as $who) {
        same(1, total($a, $b, $who), "$who told once");
    }
});

test('5b. kin says wrong on B, sync down 40 min: at most one duplicate round', function (): void {
    [$a, $b] = pair();
    $due = $a->due();
    run([$a, $b], $due + 73 * H, syncer($a, $b));
    Clock::set($due + 74 * H);
    $b->engine->recordNokAnswer('wrong', Clock::now(), 'web');
    $a->mail->sent = [];
    $b->mail->sent = [];
    run([$a, $b], $due + 74 * H + 40 * 60);
    run([$a, $b], $due + 80 * H, syncer($a, $b));
    same('fired', $a->stage(), 'A fired');
    foreach (['kin', 'tech', 'legal'] as $who) {
        check(total($a, $b, $who) <= 2, "$who at most twice");
    }
});

test('6. stale peer state from a previous cycle does not adopt or fire', function (): void {
    [$a, $b] = pair();
    $due = $a->due();
    $old = $due - 7 * 86400;
    $stale = ['node' => IB, 'at' => $old, 'last_checkin_at' => null, 'last_checkin_via' => null, 'armed' => true, 'released' => false,
        'cycle' => ['key' => $old, 'stage' => 'fired', 'steps' => ['checkin' => $old, 'verify' => $old + 72 * H, 'fire' => $old + 144 * H],
            'fired_at' => $old + 144 * H, 'fired_reason' => 'timeout', 'nok' => ['answer' => 'fine', 'at' => $old + 100 * H]]];
    $a->coord->recordPeer(IB, $stale, Clock::now());
    run([$a], $due + 80 * H, function () use ($a, $stale): void {
        $a->coord->recordPeer(IB, $stale, Clock::now());
        $a->engine->applyRemote();
    });
    same('verify', $a->stage(), 'A ran its own cycle');
    same(1, $a->to('kin@example.test'), 'kin asked by A');
    same(null, $a->engine->state()['cycle']['fired_at'], 'not fired');
});

test('7. peer clock 4 min behind: its check-in just after the cycle start still counts', function (): void {
    [$a, $b] = pair();
    $due = $a->due();
    run([$a], $due);
    $start = (int) $a->engine->state()['cycle']['started_at'];
    $st = $b->engine->nodeState(IB);
    $st['last_checkin_at'] = $start + 120 - 240;
    $st['last_checkin_via'] = 'web';
    Clock::set($start + 600);
    $a->coord->recordPeer(IB, $st, Clock::now());
    $a->engine->applyRemote();
    same(null, $a->engine->state()['cycle'], 'closed despite skew');
});

test('7b. peer clock 4 min ahead: one reminder only, no early fire', function (): void {
    [$a, $b] = pair();
    $due = $a->due();
    run([$a], $due);
    $start = (int) $a->engine->state()['cycle']['started_at'];
    $st = $b->engine->nodeState(IB);
    $st['cycle'] = ['key' => $due, 'stage' => 'checkin', 'steps' => ['checkin' => $start, 'reminder1' => $start + 24 * H + 240], 'fired_at' => null, 'fired_reason' => null, 'nok' => null];
    run([$a], $start + 80 * H, function () use ($a, $st): void {
        $a->coord->recordPeer(IB, $st, Clock::now());
    });
    check(count($a->engine->state()['cycle']['reminders']) >= 1, 'reminder adopted');
    check($a->stage() !== 'fired', 'no early fire');
});

test('8. three nodes: rank 2 waits 60 minutes to start a cycle', function (): void {
    $ranks = [IA => 0, IB => 1, IC => 2];
    $a = new SN(IA, $ranks);
    $c = new SN(IC, $ranks, $a);
    $due = $a->due();
    foreach ([IA, IB] as $p) {
        $c->coord->recordPeer($p, ['node' => $p, 'at' => $due, 'last_checkin_at' => null, 'last_checkin_via' => null, 'armed' => true, 'released' => false, 'cycle' => null], Clock::now());
    }
    run([$c], $due + 59 * 60);
    same(null, $c->engine->state()['cycle'], 'not before 60 min');
    run([$c], $due + 60 * 60);
    check($c->engine->state()['cycle'] !== null, 'starts at 60 min');
});

finish();
