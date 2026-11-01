<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Clock, Cluster, Contacts, Coordinator, Email, Engine, Messages, Outbox, Store, Transport};
require __DIR__ . '/lib.php';

const H = 3600;
const SELF = 'n_aaaaaaaa';
const PEER = 'n_bbbbbbbb';

final class Rec implements Transport
{
    public array $sent = [];
    public function send(array $smtp, array $to, string $fromName, string $replyTo, Email $email): void
    {
        $this->sent[] = ['at' => Clock::now(), 'to' => $to, 'subject' => $email->subject];
    }
}

function nd(string $id, int $rank): array
{
    return ['id' => $id, 'name' => 'N ' . $id, 'url' => 'https://a.example.org',
        'sign_pk' => rtrim(strtr(base64_encode(str_repeat('a', 32)), '+/', '-_'), '='),
        'share_pk' => 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE', 'rank' => $rank];
}

final class FW
{
    public Store $store;
    public Rec $mail;
    public Engine $engine;
    public Coordinator $coord;
    public int $due;

    /** @param int|null $selfRank null = no cluster at all besides a single node */
    public function __construct(?int $selfRank)
    {
        $dir = sys_get_temp_dir() . '/dps-fed-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        Clock::set((new \DateTimeImmutable('2026-10-07 13:00', new \DateTimeZone('Australia/Sydney')))->getTimestamp());
        $this->store = new Store($dir);
        $nodes = [nd(SELF, $selfRank ?? 0)];
        if ($selfRank !== null) {
            $nodes[] = nd(PEER, $selfRank === 0 ? 1 : 0);
        }
        Cluster::save($this->store, ['k' => 1, 'primary' => null, 'isolation_hours' => 48, 'step_delay_min' => 30, 'nodes' => $nodes]);
        $this->coord = new Coordinator($this->store, SELF);
        $this->mail = new Rec();
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
        $this->mail->sent = [];
        $this->due = (int) $this->engine->state()['next_due_at'];
    }

    public function at(int $ts): void
    {
        Clock::set($ts);
        $this->engine->tick();
    }

    public function to(string $address): int
    {
        return count(array_filter($this->mail->sent, static fn (array $m): bool => in_array($address, $m['to'], true)));
    }

    public function peer(array $state): void
    {
        $this->coord->recordPeer(PEER, $state, Clock::now());
    }

    public function nokId(): string
    {
        return (string) $this->engine->contacts()->nok()['id'];
    }
}

function pst(?array $cycle, array $more = []): array
{
    return $more + ['node' => PEER, 'at' => Clock::now(), 'last_checkin_at' => null, 'last_checkin_via' => null,
        'armed' => true, 'released' => false, 'cycle' => $cycle];
}
function pcy(int $key, string $stage, array $steps, array $more = []): array
{
    return $more + ['key' => $key, 'stage' => $stage, 'steps' => $steps, 'fired_at' => null, 'fired_reason' => null, 'nok' => null];
}

test('single node: reminder at 24 h as before', function (): void {
    $w = new FW(null);
    $w->at($w->due);
    $start = (int) $w->engine->state()['cycle']['started_at'];
    $w->at($start + 24 * H - 1);
    same([], $w->engine->state()['cycle']['reminders'], 'not yet');
    $w->at($start + 24 * H);
    same(1, count($w->engine->state()['cycle']['reminders']), 'reminder 1');
    same($start, $w->engine->state()['cycle']['steps']['checkin'], 'steps checkin');
    same($start + 24 * H, $w->engine->state()['cycle']['steps']['reminder1'], 'steps reminder1');
});

test('rank 1 waits 30 minutes for a silent peer, then sends the reminder', function (): void {
    $w = new FW(1);
    $w->peer(pst(pcy($w->due, 'checkin', ['checkin' => $w->due])));
    $w->at($w->due);
    $start = (int) $w->engine->state()['cycle']['started_at'];
    same($w->due, $start, 'cycle adopted at the peer time');
    same(0, $w->to('owner@example.test'), 'no check-in email sent by this node');
    $w->at($start + 24 * H);
    same([], $w->engine->state()['cycle']['reminders'], 'not at 24h');
    $w->at($start + 24 * H + 29 * 60);
    same([], $w->engine->state()['cycle']['reminders'], 'not at 24h29m');
    $w->at($start + 24 * H + 30 * 60);
    same(1, count($w->engine->state()['cycle']['reminders']), 'sent at 24h30m');
    same(1, $w->to('owner@example.test'), 'one email');
});

test('rank 1 adopts a reminder the peer sent, without an email', function (): void {
    $w = new FW(1);
    $w->peer(pst(pcy($w->due, 'checkin', ['checkin' => $w->due])));
    $w->at($w->due);
    $t = $w->due + 24 * H + 5 * 60;
    Clock::set($t);
    $w->peer(pst(pcy($w->due, 'checkin', ['checkin' => $w->due, 'reminder1' => $t])));
    $w->at($t + 60);
    same([$t], $w->engine->state()['cycle']['reminders'], 'adopted at peer time');
    same($t, $w->engine->state()['cycle']['steps']['reminder1'], 'step recorded');
    same(0, $w->to('owner@example.test'), 'no email');
    $w->at($t + 10 * H);
    same(1, count($w->engine->state()['cycle']['reminders']), 'nothing sent later');
});

test('isolated: the next of kin is asked at 72 h + 48 h', function (): void {
    $w = new FW(0);
    $w->at($w->due);
    $start = (int) $w->engine->state()['cycle']['started_at'];
    for ($h = 1; $h <= 119; $h++) {
        $w->at($start + $h * H);
    }
    same('checkin', $w->engine->state()['cycle']['stage'], 'still checkin at 119h');
    same(0, $w->to('kin@example.test'), 'kin not asked');
    $w->at($start + 120 * H);
    same('verify', $w->engine->state()['cycle']['stage'], 'verify at 120h');
    same(1, $w->to('kin@example.test'), 'kin asked');
});

test('not isolated: asked at 72 h when a peer is heard', function (): void {
    $w = new FW(0);
    $w->at($w->due);
    $start = (int) $w->engine->state()['cycle']['started_at'];
    for ($h = 1; $h <= 72; $h++) {
        Clock::set($start + $h * H);
        $w->peer(pst(null));
        $w->engine->tick();
    }
    same('verify', $w->engine->state()['cycle']['stage'], 'verify at 72h');
});

test('adopted verify sends nothing and later closing does not notify the kin', function (): void {
    $w = new FW(1);
    $t = $w->due + 72 * H;
    $w->peer(pst(pcy($w->due, 'verify', ['checkin' => $w->due, 'reminder1' => $w->due + 24 * H, 'reminder2' => $w->due + 48 * H, 'verify' => $t])));
    $w->at($w->due);
    $w->at($w->due + 24 * H);
    $w->at($w->due + 48 * H);
    $w->at($w->due + 72 * H);
    $c = $w->engine->state()['cycle'];
    same('verify', $c['stage'], 'verify adopted');
    same($t, $c['verify']['asked_at'], 'peer time');
    same(0, $w->to('kin@example.test'), 'kin not asked by this node');
    // The peer owner checks in later: nobody is told by this node.
    Clock::set($t + 5 * H);
    $w->peer(pst(null, ['last_checkin_at' => $t + 4 * H, 'last_checkin_via' => 'the emailed link']));
    $w->engine->applyRemote();
    $s = $w->engine->state();
    same(null, $s['cycle'], 'cycle closed');
    same($t + 4 * H, $s['last_checkin_at'], 'checkin time');
    same(0, $w->to('kin@example.test'), 'no all-clear from a node that never told');
    same([], $w->engine->applyRemote(), 'idempotent');
});

test('a peer check-in closes a cycle this node did tell about, once', function (): void {
    $w = new FW(0);
    $w->at($w->due);
    $start = (int) $w->engine->state()['cycle']['started_at'];
    for ($h = 1; $h <= 72 + 48; $h++) {
        $w->at($start + $h * H);
    }
    same('verify', $w->engine->state()['cycle']['stage'], 'verify');
    same(1, $w->to('kin@example.test'), 'asked');
    Clock::set($start + 121 * H);
    $w->peer(pst(null, ['last_checkin_at' => $start + 120 * H + 60, 'last_checkin_via' => 'web']));
    $w->engine->applyRemote();
    $w->engine->applyRemote();
    same(null, $w->engine->state()['cycle'], 'closed');
    same('another node', substr((string) $w->engine->state()['last_checkin_via'], 0, 12), 'via');
    same(2, $w->to('kin@example.test'), 'asked once plus one all-clear');
});

test('a peer that fired: local fires with its reason, no duplicate mail to contacts it told', function (): void {
    $w = new FW(1);
    $key = $w->due;
    $t = $w->due + 144 * H;
    $nok = $w->nokId();
    $w->peer(pst(pcy($key, 'verify', ['checkin' => $key, 'reminder1' => $key + 24 * H, 'reminder2' => $key + 48 * H, 'verify' => $key + 72 * H])));
    foreach ([0, 24, 48, 72] as $h) {
        $w->at($key + $h * H);
    }
    same('verify', $w->engine->state()['cycle']['stage'], 'verify');
    Clock::set($t);
    $w->peer(pst(pcy($key, 'fired', ['checkin' => $key, 'verify' => $key + 72 * H, 'fire' => $t, 'deliver:' . $nok => $t + 60],
        ['fired_at' => $t, 'fired_reason' => 'nok_wrong'])));
    $log = $w->engine->applyRemote();
    check($log !== [], 'something changed');
    $c = $w->engine->state()['cycle'];
    same('fired', $c['stage'], 'fired');
    same('nok_wrong', $c['fired_reason'], 'reason');
    same($t, $c['fired_at'], 'time');
    same($t + 60, $c['notified'][$nok], 'adopted delivery');
    same([], $w->engine->applyRemote(), 'idempotent');
    $w->at($t + 10 * 60);
    same(0, $w->to('tech@example.test'), 'rank 1 waits');
    $w->at($t + 30 * 60);
    same(1, $w->to('tech@example.test'), 'tech told after the delay');
    same(0, $w->to('kin@example.test'), 'kin not told twice');
});

test('a stale peer state for another cycle changes nothing', function (): void {
    $w = new FW(0);
    $w->at($w->due);
    $old = $w->due - 7 * 86400;
    $w->peer(pst(pcy($old, 'fired', ['fire' => $old, 'verify' => $old], ['fired_at' => $old, 'fired_reason' => 'timeout'])));
    same([], $w->engine->applyRemote(), 'no change');
    same('checkin', $w->engine->state()['cycle']['stage'], 'stage unchanged');
});

test('recordNokAnswer applies fine and wrong, and ignores everything else', function (): void {
    $w = new FW(0);
    $w->engine->recordNokAnswer('fine', Clock::now(), 'node');
    same(null, $w->engine->state()['cycle'], 'no cycle: ignored');
    $w->at($w->due);
    $start = (int) $w->engine->state()['cycle']['started_at'];
    $w->engine->recordNokAnswer('fine', Clock::now(), 'node');
    same('checkin', $w->engine->state()['cycle']['stage'], 'no verify cycle: ignored');
    for ($h = 1; $h <= 120; $h++) {
        $w->at($start + $h * H);
    }
    $w->engine->recordNokAnswer('maybe', Clock::now(), 'node');
    same('verify', $w->engine->state()['cycle']['stage'], 'bad answer ignored');
    $w->engine->recordNokAnswer('fine', Clock::now(), 'node');
    same(null, $w->engine->state()['cycle'], 'fine ends the cycle');

    $w2 = new FW(0);
    $w2->at($w2->due);
    $s2 = (int) $w2->engine->state()['cycle']['started_at'];
    for ($h = 1; $h <= 120; $h++) {
        $w2->at($s2 + $h * H);
    }
    $w2->engine->recordNokAnswer('wrong', Clock::now(), 'node');
    same('fired', $w2->engine->state()['cycle']['stage'], 'wrong fires');
    same('nok_wrong', $w2->engine->state()['cycle']['fired_reason'], 'reason');
});

test('nodeState has the documented shape', function (): void {
    $w = new FW(0);
    $s = $w->engine->nodeState(SELF);
    same(SELF, $s['node'], 'node');
    same(true, $s['armed'], 'armed');
    same(false, $s['released'], 'released');
    same(null, $s['cycle'], 'no cycle');
    check(array_key_exists('last_checkin_at', $s) && array_key_exists('last_checkin_via', $s) && array_key_exists('at', $s), 'keys');
    $w->at($w->due);
    $c = $w->engine->nodeState(SELF)['cycle'];
    same($w->due, $c['key'], 'key');
    same('checkin', $c['stage'], 'stage');
    same(['checkin'], array_keys($c['steps']), 'steps');
    same(null, $c['fired_at'], 'fired_at');
    same(null, $c['nok'], 'nok');
});

test('checkInFromPeer stamps the peer\'s time, not the arrival time', function (): void {
    $w = new FW(0);
    $w->at($w->due);
    $start = (int) $w->engine->state()['cycle']['started_at'];
    Clock::set($start + 5 * H);
    $at = $start + 2 * H;
    $r = $w->engine->checkInFromPeer($at, 'another node');
    check($r !== null, 'accepted');
    $s = $w->engine->state();
    same($at, $s['last_checkin_at'], 'peer time, not now');
    same('another node', $s['last_checkin_via'], 'via');
    same(null, $s['cycle'], 'cycle closed');
    check((int) $s['next_due_at'] > $at, 'next slot follows the peer time');
    same($s['next_due_at'], $r['next_due_at'], 'returned');
});

test('checkInFromPeer ignores a check-in older than the one held', function (): void {
    $w = new FW(0);
    $w->at($w->due);
    $start = (int) $w->engine->state()['cycle']['started_at'];
    Clock::set($start + 5 * H);
    $w->engine->checkInFromPeer($start + 3 * H, 'another node');
    $before = $w->engine->state();
    $w->at($w->due);
    $cycle = $w->engine->state()['cycle'];
    $r = $w->engine->checkInFromPeer($start + 2 * H, 'another node');
    same(null, $r, 'ignored');
    $after = $w->engine->state();
    same($before['last_checkin_at'], $after['last_checkin_at'], 'time kept');
    same($cycle, $after['cycle'], 'cycle untouched');
});

test('a cut-off node warns about its peers, a connected one does not', function (): void {
    $w = new FW(0);
    $keys = fn (): array => array_column($w->engine->warnings(), 'key');
    check(in_array('peers', $keys(), true), 'warns when no peer was ever heard');
    check(in_array('peers', $w->engine->health()['problems'], true), 'health carries it');
    $w->peer(pst(null));
    check(!in_array('peers', $keys(), true), 'quiet once a peer is heard');
    Clock::set(Clock::now() + 7 * H);
    $warn = array_values(array_filter($w->engine->warnings(), fn ($x) => $x['key'] === 'peers'));
    same(1, count($warn), 'warns again after 6 h of silence');
    same('bad', $warn[0]['level'], 'level');
    check(str_contains($warn[0]['text'], 'for 7 hours'), 'says how long: ' . $warn[0]['text']);
});

test('a single node never warns about peers', function (): void {
    $w = new FW(null);
    check(!in_array('peers', array_column($w->engine->warnings(), 'key'), true), 'no peers warning');
});

finish();
