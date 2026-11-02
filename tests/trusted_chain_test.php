<?php
/**
 * The chain of trusted people: who is asked, when, and when the switch may fire.
 * Single-node tests use a simulated clock and a mail recorder; the federation tests use two nodes.
 */
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Clock, Cluster, Contacts, Coordinator, Email, Engine, Messages, Outbox, Schedule, Store, Transport, Trust};
require __DIR__ . '/lib.php';

const H = 3600;
const IA = 'n_aaaaaaaa';
const IB = 'n_bbbbbbbb';

final class TRec implements Transport
{
    public array $sent = [];
    public function send(array $smtp, array $to, string $fromName, string $replyTo, Email $email): void
    {
        $this->sent[] = ['at' => Clock::now(), 'to' => $to, 'subject' => $email->subject, 'link' => $email->firstLink(), 'text' => $email->text()];
    }
}

function tnode(string $id, int $rank): array
{
    return ['id' => $id, 'name' => 'N ' . $id, 'url' => 'https://a.example.org',
        'sign_pk' => rtrim(strtr(base64_encode(str_repeat('a', 32)), '+/', '-_'), '='),
        'share_pk' => 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE', 'rank' => $rank];
}

/** One node with $people trusted people (the first is the next of kin), optionally in a cluster. */
final class TW
{
    public Store $store;
    public TRec $mail;
    public Engine $engine;
    public ?Coordinator $coord = null;
    /** @var list<string> contact ids in chain order */
    public array $ids = [];

    public function __construct(int $people, array $trust = [], ?array $ranks = null, ?string $id = null, ?TW $copyFrom = null, bool $extras = false)
    {
        $dir = sys_get_temp_dir() . '/dps-chain-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        Clock::set((new \DateTimeImmutable('2026-10-07 13:00', new \DateTimeZone('Australia/Sydney')))->getTimestamp());
        $this->store = new Store($dir);
        if ($ranks !== null) {
            $nodes = [];
            foreach ($ranks as $nid => $r) {
                $nodes[] = tnode($nid, $r);
            }
            Cluster::save($this->store, ['k' => 1, 'primary' => null, 'isolation_hours' => 48, 'step_delay_min' => 30, 'nodes' => $nodes]);
            $this->coord = new Coordinator($this->store, (string) $id);
        }
        $this->mail = new TRec();
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
        ] + ($trust === [] ? [] : ['trust' => $trust]));
        if ($copyFrom !== null) {
            $this->store->write('contacts', $copyFrom->store->read('contacts'));
            $this->ids = $copyFrom->ids;
        } else {
            $book = $this->engine->contacts();
            for ($i = 1; $i <= $people; $i++) {
                $c = $this->make($i === 1 ? 'nok' : 'other', "P{$i}", "p{$i}@example.test");
                $this->ids[] = $c['id'];
                if ($i > 1) {
                    $book->setTrusted($c['id'], true);
                }
            }
            if ($extras) {
                $this->make('technical', 'Tao', 'tech@example.test');
            }
        }
        $this->engine->tick();
        $errors = $this->engine->arm();
        if ($errors) {
            throw new \RuntimeException('Could not arm: ' . implode('; ', $errors));
        }
        $this->mail->sent = [];
    }

    private function make(string $kind, string $name, string $email): array
    {
        $c = Contacts::blank($kind);
        $c['name'] = $name;
        $c['email'] = $email;
        $c['status'] = 'confirmed';
        $c['confirmed_at'] = $c['reconfirmed_at'] = $c['has_needs_at'] = Clock::now();
        $c['message']['body'] = preg_replace(Messages::MARKER, 'the blue folder', $c['message']['body']);
        $this->engine->contacts()->save($c);
        return $c;
    }

    /** Tick every 15 minutes up to and including $until (a timestamp). */
    public function run(int $until): void
    {
        for ($t = Clock::now(); $t <= $until; $t += 900) {
            Clock::set($t);
            $this->engine->tick();
        }
        Clock::set($until);
        $this->engine->tick();
    }

    /** Tick once at $t exactly. */
    public function at(int $t): void
    {
        Clock::set($t);
        $this->engine->tick();
    }

    /** Run until the first person has been asked; returns that time. */
    public function toVerify(): int
    {
        $due = (int) $this->engine->state()['next_due_at'];
        for ($t = $due; $t < $due + 30 * 24 * H; $t += 900) {
            Clock::set($t);
            $this->engine->tick();
            if (($this->engine->state()['cycle']['stage'] ?? '') === 'verify') {
                return (int) $this->engine->state()['cycle']['verify']['asked_at'];
            }
        }
        throw new \RuntimeException('never reached verify');
    }

    public function cycle(): ?array
    {
        return $this->engine->state()['cycle'];
    }

    public function stage(): ?string
    {
        return $this->cycle()['stage'] ?? null;
    }

    /** Questions sent to person $n (1-based). */
    public function asked(int $n): int
    {
        return count(array_filter($this->mail->sent, static fn (array $m): bool =>
            in_array("p{$n}@example.test", $m['to'], true) && str_contains($m['subject'], 'all right?') && !str_starts_with($m['subject'], 'Reminder')));
    }

    /** Time person $n was first asked, or null. */
    public function askedAt(int $n): ?int
    {
        foreach ($this->mail->sent as $m) {
            if (in_array("p{$n}@example.test", $m['to'], true) && str_contains($m['subject'], 'all right?') && !str_starts_with($m['subject'], 'Reminder')) {
                return $m['at'];
            }
        }
        return null;
    }

    /** The newest answer token sent to person $n. */
    public function token(int $n): string
    {
        $tok = null;
        foreach ($this->mail->sent as $m) {
            if (in_array("p{$n}@example.test", $m['to'], true) && str_contains($m['subject'], 'all right?')) {
                parse_str((string) parse_url((string) $m['link'], PHP_URL_QUERY), $q);
                $tok = (string) $q['t'];
            }
        }
        if ($tok === null) {
            throw new \RuntimeException("person $n was not asked");
        }
        return $tok;
    }

    /** Messages sent when the switch fires, to person $n. */
    public function firedTo(int $n): int
    {
        return count(array_filter($this->mail->sent, static fn (array $m): bool =>
            in_array("p{$n}@example.test", $m['to'], true) && !str_contains($m['subject'], 'all right?') && !str_contains($m['subject'], 'False alarm')));
    }

    public function allClear(int $n): int
    {
        return count(array_filter($this->mail->sent, static fn (array $m): bool =>
            in_array("p{$n}@example.test", $m['to'], true) && str_contains($m['subject'], 'False alarm')));
    }

    public function hear(TW $from): void
    {
        $this->coord->recordPeer($from->idOf(), $from->engine->nodeState($from->idOf()), Clock::now());
        $this->engine->applyRemote();
    }

    private string $nodeId = '';

    public function idOf(): string
    {
        return $this->nodeId;
    }

    public function named(string $id): self
    {
        $this->nodeId = $id;
        return $this;
    }
}

function pairOf(int $people, array $trust = []): array
{
    $ranks = [IA => 0, IB => 1];
    $a = (new TW($people, $trust, $ranks, IA))->named(IA);
    $b = (new TW($people, $trust, $ranks, IB, $a))->named(IB);
    return [$a, $b];
}

function both(TW $a, TW $b, int $until, ?callable $sync = null): void
{
    for ($t = Clock::now(); $t <= $until; $t += 900) {
        Clock::set($t);
        if ($sync !== null) {
            $sync();
        }
        $a->engine->tick();
        $b->engine->tick();
    }
}

function syncAB(TW $a, TW $b): callable
{
    return function () use ($a, $b): void {
        $a->hear($b);
        $b->hear($a);
    };
}

// ---------------------------------------------------------------------------
// The list and its settings
// ---------------------------------------------------------------------------

test('an old data file with only a next of kin loads with that person as trusted number one', function (): void {
    $dir = sys_get_temp_dir() . '/dps-chain-' . bin2hex(random_bytes(6));
    mkdir($dir, 0700, true);
    $store = new Store($dir);
    $old = [['id' => 'c_old', 'kind' => 'nok', 'role_label' => 'Next of kin', 'name' => 'Kim', 'email' => 'kim@example.test', 'phone' => '',
        'status' => 'confirmed', 'created_at' => 1000, 'message' => ['subject' => 's', 'body' => 'b']],
        ['id' => 'c_tech', 'kind' => 'technical', 'role_label' => 'Technical helper', 'name' => 'Tao', 'email' => 'tao@example.test', 'phone' => '',
        'status' => 'confirmed', 'created_at' => 1001, 'message' => ['subject' => 's', 'body' => 'b']]];
    $store->write('contacts', ['contacts' => $old]);
    $book = new Contacts($store);
    same(['c_old'], array_column($book->trusted(), 'id'), 'the next of kin is the only trusted person');
    same(1, $book->trustedNumber('c_old'), 'number one');
    same(null, $book->trustedNumber('c_tech'), 'the technical helper is not trusted');
    same($old, $store->read('contacts')['contacts'], 'loading changed nothing on disk');
});

test('trusted people keep an order, the next of kin stays first, and the buttons move people', function (): void {
    $w = new TW(4);
    $book = $w->engine->contacts();
    same($w->ids, array_column($book->trusted(), 'id'), 'in the order they were added');
    $book->move($w->ids[3], -1);
    same([$w->ids[0], $w->ids[1], $w->ids[3], $w->ids[2]], array_column($book->trusted(), 'id'), 'moved up');
    $book->move($w->ids[1], -1);
    same($w->ids[0], $book->trusted()[0]['id'], 'nobody moves above the next of kin');
    $book->move($w->ids[0], 1);
    same($w->ids[0], $book->trusted()[0]['id'], 'and the next of kin does not move down');
    $book->setTrusted($w->ids[2], false);
    same([$w->ids[0], $w->ids[1], $w->ids[3]], array_column($book->trusted(), 'id'), 'untrusted');
    $book->setTrusted($w->ids[0], false);
    same($w->ids[0], $book->trusted()[0]['id'], 'the next of kin cannot be untrusted');
});

test('the automatic interval spreads the asks over twelve hours, in whole minutes', function (): void {
    same(43200, Trust::autoStep(1), '1 person');
    same(43200, Trust::autoStep(2), '2 people');
    same(21600, Trust::autoStep(3), '3 people');
    same(8640, Trust::autoStep(6), '6 people: 2.4 h');
    same(3900, Trust::autoStep(12), '12 people: 65 minutes');
});

test('settings are clamped to their limits and default sensibly', function (): void {
    same(['mode' => 'sequential', 'interval' => null, 'window' => 24], Trust::settings([]), 'defaults');
    same(['mode' => 'all_at_once', 'interval' => 15, 'window' => 6], Trust::settings(['trust' => ['mode' => 'all_at_once', 'interval' => 1, 'window' => 1]]), 'low');
    same(['mode' => 'sequential', 'interval' => 1440, 'window' => 72], Trust::settings(['trust' => ['mode' => 'nonsense', 'interval' => 99999, 'window' => 999]]), 'high');
    same(null, Trust::settings(['trust' => ['interval' => 'auto']])['interval'], 'auto');
});

// ---------------------------------------------------------------------------
// Sequential mode
// ---------------------------------------------------------------------------

test('one person: asked once, fire time is unchanged (72 hours)', function (): void {
    $w = new TW(1);
    $v = $w->toVerify();
    $w->run($v + 71 * H);
    same('verify', $w->stage(), 'still waiting at 71 h');
    $w->run($v + 72 * H + 1800);
    same('fired', $w->stage(), 'fired at 72 h');
    same(1, $w->asked(1), 'asked once');
});

test('two people: the second is asked 12 hours after the first, fire time unchanged', function (): void {
    $w = new TW(2);
    $v = $w->toVerify();
    $w->run($v + 12 * H - 1);
    same(0, $w->asked(2), 'not before 12 h');
    $w->at($v + 12 * H);
    same($v + 12 * H, $w->askedAt(2), 'asked at exactly 12 h');
    same($v + 72 * H, Engine::plan($w->cycle(), null)['fire']['at'], 'fire at 72 h as before');
});

test('six people: one every 2.4 hours, exactly', function (): void {
    $w = new TW(6);
    $v = $w->toVerify();
    for ($k = 1; $k < 6; $k++) {
        $at = $v + $k * 8640;
        $w->run($at - 1);
        same(0, $w->asked($k + 1), "person " . ($k + 1) . " not before their time");
        $w->at($at);
        same($at, $w->askedAt($k + 1), "person " . ($k + 1) . " asked on time");
        same(1, $w->asked($k + 1), 'once');
    }
    same($v + 72 * H, Engine::plan($w->cycle(), null)['fire']['at'], 'fire time still 72 h (5 x 2.4 h + 24 h is less)');
});

test('twelve people: one every 65 minutes', function (): void {
    $w = new TW(12);
    $v = $w->toVerify();
    for ($k = 1; $k < 12; $k++) {
        $w->run($v + $k * 3900 - 1);
        same(0, $w->asked($k + 1), 'not early');
        $w->at($v + $k * 3900);
        same($v + $k * 3900, $w->askedAt($k + 1), 'person ' . ($k + 1) . ' on time');
    }
});

test('a fixed interval is used as set', function (): void {
    $w = new TW(3, ['mode' => 'sequential', 'interval' => 30, 'window' => 24]);
    $v = $w->toVerify();
    $w->run($v + 30 * 60 - 1);
    same(0, $w->asked(2), 'not before 30 min');
    $w->at($v + 30 * 60);
    same($v + 1800, $w->askedAt(2), 'second at 30 min');
    $w->run($v + 3600 - 1);
    same(0, $w->asked(3), 'third not early');
    $w->at($v + 3600);
    same($v + 3600, $w->askedAt(3), 'third at 60 min after the first');
});

test('a late tick never bunches asks together', function (): void {
    $w = new TW(3, ['mode' => 'sequential', 'interval' => 60, 'window' => 24]);
    $v = $w->toVerify();
    $w->at($v + 10 * H);
    same(1, $w->asked(2), 'second asked by the late tick');
    same(0, $w->asked(3), 'third not at the same time');
    $w->at($v + 10 * H + 3599);
    same(0, $w->asked(3), 'third waits its interval after the second');
    $w->at($v + 10 * H + 3600);
    same(1, $w->asked(3), 'third asked');
});

test('every asked person gets the verify email addressed to them', function (): void {
    $w = new TW(3);
    $v = $w->toVerify();
    $w->run($v + 13 * H);
    for ($n = 1; $n <= 3; $n++) {
        same(1, $w->asked($n), "P$n asked once");
    }
    $text = '';
    foreach ($w->mail->sent as $m) {
        if (in_array('p2@example.test', $m['to'], true) && str_contains($m['subject'], 'all right?')) {
            $text = $m['text'];
        }
    }
    check(str_contains($text, 'respond.php?t='), 'with its own answer link');
    check($w->token(2) !== $w->token(1), 'each person has their own link');
});

test('the fire time waits for the last person plus the reply window', function (): void {
    $w = new TW(12, ['mode' => 'sequential', 'interval' => 1440, 'window' => 24]);
    $v = $w->toVerify();
    $last = $v + 11 * 24 * H;
    same($last + 24 * H, Engine::plan($w->cycle(), null)['fire']['at'], 'projected fire time');
    $w->run($last + 24 * H - 900);
    same('verify', $w->stage(), 'not fired one tick before');
    same(1, $w->asked(12), 'the last was asked');
    $w->run($last + 24 * H + 900);
    same('fired', $w->stage(), 'fired after the window');
    same(1, $w->firedTo(12), 'everyone is sent their message once');
});

test('the reply window can be set from 6 to 72 hours', function (): void {
    $w = new TW(2, ['mode' => 'sequential', 'interval' => 'auto', 'window' => 72]);
    $v = $w->toVerify();
    same($v + 12 * H + 72 * H, Engine::plan($w->cycle(), null)['fire']['at'], '72 h window');
    $w2 = new TW(2, ['mode' => 'sequential', 'interval' => 'auto', 'window' => 6]);
    $v2 = $w2->toVerify();
    same($v2 + 72 * H, Engine::plan($w2->cycle(), null)['fire']['at'], 'a short window never brings the fire time forward');
});

test('the dashboard plan lists every ask before the check-in even goes overdue', function (): void {
    $w = new TW(3);
    $plan = $w->engine->status()['plan'];
    check(isset($plan['ask:' . $w->ids[1]]) && isset($plan['ask:' . $w->ids[2]]), 'asks are projected');
    same($plan['verify']['at'] + 21600, $plan['ask:' . $w->ids[1]]['at'], 'second is 6 h after the first (3 people)');
    same(false, $plan['ask:' . $w->ids[1]]['done'], 'not done');
});

// ---------------------------------------------------------------------------
// All at once
// ---------------------------------------------------------------------------

test('all at once: the first alone, then everyone else after twelve hours', function (): void {
    $w = new TW(4, ['mode' => 'all_at_once', 'interval' => 'auto', 'window' => 24]);
    $v = $w->toVerify();
    $w->run($v + 12 * H - 1);
    same([1, 0, 0, 0], [$w->asked(1), $w->asked(2), $w->asked(3), $w->asked(4)], 'only the first until 12 h');
    $w->at($v + 12 * H);
    same([1, 1, 1, 1], [$w->asked(1), $w->asked(2), $w->asked(3), $w->asked(4)], 'all asked together');
    same($v + 12 * H, $w->askedAt(4), 'at the same moment');
});

test('all at once with the owner\'s interval', function (): void {
    $w = new TW(3, ['mode' => 'all_at_once', 'interval' => 90, 'window' => 24]);
    $v = $w->toVerify();
    $w->run($v + 90 * 60 - 1);
    same(0, $w->asked(2) + $w->asked(3), 'nobody yet');
    $w->at($v + 90 * 60);
    same(1, $w->asked(2) + $w->asked(3) - 1, 'both asked');
});

// ---------------------------------------------------------------------------
// Answers
// ---------------------------------------------------------------------------

test('"fine" from the third person closes the cycle: nobody else is asked, nothing fires', function (): void {
    $w = new TW(6);
    $v = $w->toVerify();
    $w->run($v + 6 * H);
    same(1, $w->asked(3), 'the third was asked');
    $token = $w->token(3);
    same('fine', $w->engine->nokAnswer($token, 'fine'), 'recorded');
    same(null, $w->cycle(), 'cycle closed');
    $w->run($v + 40 * H);
    same(0, $w->asked(4) + $w->asked(5) + $w->asked(6), 'the rest were never asked');
    for ($n = 1; $n <= 6; $n++) {
        same(0, $w->firedTo($n), "P$n not told the switch fired");
    }
    same(1, $w->allClear(1), 'the first, asked and silent, gets the all clear');
    same(1, $w->allClear(2), 'and the second');
    same(0, $w->allClear(3), 'the one who answered does not');
    same(0, $w->allClear(4), 'nor anyone not asked');
});

test('"fine" from the only person: they get no false-alarm notice for their own answer', function (): void {
    $w = new TW(1);
    $w->toVerify();
    same('fine', $w->engine->nokAnswer($w->token(1), 'fine'), 'recorded');
    same(0, $w->allClear(1), 'no notice to the one who answered');
    same(null, $w->cycle(), 'closed');
});

test('"wrong" from the next of kin fires at once, even with three people', function (): void {
    $w = new TW(3);
    $v = $w->toVerify();
    $w->run($v + H);
    same('wrong', $w->engine->nokAnswer($w->token(1), 'wrong'), 'recorded');
    same('fired', $w->stage(), 'fired now');
    same('nok_wrong', $w->cycle()['fired_reason'], 'for that reason');
});

test('"wrong" from the second person never fires early and the third is still asked', function (): void {
    $w = new TW(3);
    $v = $w->toVerify();
    $w->run($v + 7 * H);
    same(1, $w->asked(2), 'the second was asked');
    same('wrong', $w->engine->nokAnswer($w->token(2), 'wrong'), 'recorded');
    same('verify', $w->stage(), 'not fired');
    same(0, $w->firedTo(3), 'nothing sent early');
    same(true, $w->engine->verifyTokenIsLive($w->token(2)), 'the link still takes a later "fine"');
    $w->run($v + 14 * H);
    same(1, $w->asked(3), 'the third is asked on schedule');
    same('fine', $w->engine->nokAnswer($w->token(3), 'fine'), 'a later "fine" still closes the cycle');
    same(null, $w->cycle(), 'closed');
    $text = '';
    foreach ($w->mail->sent as $m) {
        if (in_array('p1@example.test', $m['to'], true) && str_contains($m['subject'], 'False alarm')) {
            $text = $m['text'];
        }
    }
    check(str_contains($text, 'P3 has confirmed that Sam Rivers is all right') && !str_contains($text, 'checked in'), 'the notice names who confirmed and does not claim a check-in: ' . $text);
});

test('"wrong" then "fine" from the second person: the "fine" still closes the cycle', function (): void {
    $w = new TW(3);
    $v = $w->toVerify();
    $w->run($v + 7 * H);
    same('wrong', $w->engine->nokAnswer($w->token(2), 'wrong'), 'wrong recorded');
    Clock::set($v + 8 * H);
    same('fine', $w->engine->nokAnswer($w->token(2), 'fine'), 'the same link takes the "fine"');
    same(null, $w->cycle(), 'closed');
    $w->run($v + 100 * H);
    same(0, $w->asked(3), 'the third is never asked');
    same(0, $w->firedTo(1) + $w->firedTo(2) + $w->firedTo(3), 'nothing fired');
});

test('the next of kin unconfirmed: the person asked first cannot fire early, and the page says so', function (): void {
    $w = new TW(3);
    $book = $w->engine->contacts();
    $nok = $book->find($w->ids[0]);
    $nok['status'] = 'pending';
    $book->save($nok);
    $v = $w->toVerify();
    same([$w->ids[1], $w->ids[2]], $w->cycle()['verify']['chain'], 'the second person heads this cycle');
    same(0, $w->asked(1), 'the unconfirmed next of kin is not asked');
    $role = $w->engine->verifyAnswerer($w->token(2));
    same([1, false], [$role['position'], $role['fires']], 'first in the chain, but no right to fire at once');
    same('wrong', $w->engine->nokAnswer($w->token(2), 'wrong'), 'recorded');
    same('verify', $w->stage(), 'not fired');
    same(0, $w->firedTo(3), 'nothing sent early');
    $w->run($v + 13 * H);
    same(1, $w->asked(3), 'the next person is still asked');
});

test('a "wrong" from someone removed from the contacts mid-cycle fires nothing early', function (): void {
    $w = new TW(3);
    $v = $w->toVerify();
    $w->run($v + 7 * H);
    $token = $w->token(2);
    $w->engine->contacts()->delete($w->ids[1]);
    same('wrong', $w->engine->nokAnswer($token, 'wrong'), 'recorded');
    same('verify', $w->stage(), 'not fired');
    same(null, $w->cycle()['fired_at'], 'no fire time');
    $w2 = new TW(2);
    $w2->toVerify();
    $token = $w2->token(1);
    $w2->engine->contacts()->delete($w2->ids[0]);
    $w2->engine->nokAnswer($token, 'wrong');
    same('verify', $w2->stage(), 'not even when the removed person was the next of kin');
});

test('a long chain keeps the first person\'s link valid until after the fire time', function (): void {
    $w = new TW(37, ['mode' => 'sequential', 'interval' => 1440, 'window' => 24]);
    $v = $w->toVerify();
    same($v + 37 * 24 * H, Engine::plan($w->cycle(), null)['fire']['at'], '36 gaps of a day, plus the window');
    for ($t = $v; $t <= $v + 36 * 24 * H; $t += H) {
        $w->at($t);
    }
    same('verify', $w->stage(), 'still asking');
    same('fine', $w->engine->nokAnswer($w->token(1), 'fine'), 'the first person\'s "fine" is still taken after 36 days');
    same(null, $w->cycle(), 'closed');
});

test('"wrong" from the only trusted person fires at once, as before', function (): void {
    $w = new TW(1, [], null, null, null, true);
    $w->toVerify();
    same('wrong', $w->engine->nokAnswer($w->token(1), 'wrong'), 'recorded');
    same('fired', $w->stage(), 'fired');
});

test('an answer from someone not asked yet, or from an old cycle, is ignored', function (): void {
    $w = new TW(3);
    $v = $w->toVerify();
    same(false, $w->engine->verifyTokenIsLive('made-up-token'), 'unknown link');
    same(null, $w->engine->nokAnswer('made-up-token', 'fine'), 'ignored');
    $old = $w->token(1);
    $w->engine->checkIn('web');
    same(null, $w->engine->nokAnswer($old, 'fine'), 'the old cycle\'s link does nothing');
    // the next cycle: the same person has a new link and the old one stays dead
    $w->run((int) $w->engine->state()['next_due_at'] + 80 * H);
    same('verify', $w->stage(), 'next cycle at verify');
    same(null, $w->engine->nokAnswer($old, 'fine'), 'still dead');
    same(null, $w->engine->nokAnswer($w->token(1) . 'x', 'fine'), 'a changed link does nothing');
    same('verify', $w->stage(), 'cycle intact');
    // a peer's "fine" from before this cycle's first question, or from another cycle, is not taken
    $first = (int) $w->cycle()['verify']['asked_at'];
    $key = (int) $w->cycle()['due_at'];
    $w->engine->recordNokAnswer('fine', $first - 601, 'node');
    same('verify', $w->stage(), 'an old "fine" with no cycle key does not close this cycle');
    $w->engine->recordNokAnswer('fine', Clock::now(), 'node', $key - 7 * 24 * H);
    same('verify', $w->stage(), 'a "fine" from the previous cycle does not close this one');
    $w->engine->recordNokAnswer('fine', Clock::now(), 'node', $key);
    same(null, $w->cycle(), 'a "fine" from this very cycle does');
});

test('a check-in tells everyone asked that it was a false alarm', function (): void {
    $w = new TW(3);
    $v = $w->toVerify();
    $w->run($v + 7 * H);
    $r = $w->engine->checkIn('web');
    sort($r['told']);
    same(['P1', 'P2'], $r['told'], 'those asked are told');
    same(0, $w->allClear(3), 'not the one never asked');
});

// ---------------------------------------------------------------------------
// Federation
// ---------------------------------------------------------------------------

test('two nodes: each person is asked once, by the first node', function (): void {
    [$a, $b] = pairOf(4);
    $due = (int) $a->engine->state()['next_due_at'];
    both($a, $b, $due + 90 * H, syncAB($a, $b));
    for ($n = 1; $n <= 4; $n++) {
        same(1, $a->asked($n) + $b->asked($n), "P$n asked once across both nodes");
    }
    same(0, $b->asked(2) + $b->asked(3) + $b->asked(4), 'all by A');
    $steps = $b->engine->nodeState(IB)['cycle']['steps'];
    check(isset($steps['ask:' . $b->ids[1]]), 'B reports the ask it adopted');
});

test('B is partitioned: A asks the second person, B adopts it after sync without re-asking', function (): void {
    [$a, $b] = pairOf(3);
    $due = (int) $a->engine->state()['next_due_at'];
    both($a, $b, $due + 73 * H, syncAB($a, $b));
    same('verify', $b->stage(), 'B is in verify');
    $v = (int) $a->cycle()['verify']['asked_at'];
    same(1, $a->asked(1), 'first asked by A');
    // partition: only A runs until after it asked the second person
    for ($t = Clock::now(); $t <= $v + 6 * H + 600; $t += 900) {
        Clock::set($t);
        $a->engine->tick();
    }
    same(1, $a->asked(2), 'A asked the second');
    // the link comes back within B's rank delay
    Clock::set($v + 6 * H + 900);
    $b->hear($a);
    $b->engine->tick();
    same(0, $b->asked(2), 'B did not ask again');
    $adopted = $b->cycle()['verify']['asks'][$b->ids[1]] ?? null;
    check($adopted !== null && ($adopted['asked_at'] ?? 0) === $a->askedAt(2), 'B adopted the time A asked');
    both($a, $b, $v + 20 * H, syncAB($a, $b));
    same(1, $a->asked(3) + $b->asked(3), 'the third is asked once too');
});

test('B never fires, or asks early, because of A\'s chain state alone', function (): void {
    $ranks = [IA => 0, IB => 1];
    $a = (new TW(3, [], $ranks, IA))->named(IA);
    $b = (new TW(3, [], $ranks, IB, $a))->named(IB);
    $due = (int) $b->engine->state()['next_due_at'];
    $v = $due + 72 * H;
    $steps = ['checkin' => $due, 'verify' => $v, 'ask:' . $b->ids[1] => $v + 1, 'ask:' . $b->ids[2] => $v + 2, 'fire' => $v + 3];
    $claim = ['node' => IA, 'at' => 0, 'last_checkin_at' => null, 'last_checkin_via' => null, 'armed' => true, 'released' => false,
        'cycle' => ['key' => $due, 'stage' => 'fired', 'steps' => $steps, 'fired_at' => $v + 3, 'fired_reason' => 'timeout', 'nok' => null]];
    $feed = function () use ($b, $claim): void {
        $claim['at'] = Clock::now();
        $b->coord->recordPeer(IA, $claim, Clock::now());
        $b->engine->applyRemote();
    };
    for ($t = $due; $t <= $due + 72 * H + 4 * H; $t += 900) {
        Clock::set($t);
        $feed();
        $b->engine->tick();
    }
    $asked = (int) ($b->cycle()['verify']['asked_at'] ?? 0);
    check($asked >= $due + 72 * H, 'B asked the first person on its own timeline');
    same('verify', $b->stage(), 'not fired on A\'s say-so');
    same(0, $b->asked(2) + $b->asked(3), 'the others are not asked at A\'s early times');
    for ($t = Clock::now(); $t <= $asked + 71 * H; $t += 900) {
        Clock::set($t);
        $feed();
        $b->engine->tick();
    }
    $second = $b->cycle()['verify']['asks'][$b->ids[1]]['asked_at'] ?? null;
    check(is_int($second) && $second >= $asked + 6 * H, 'B asked the second on its own schedule, not at A\'s early time: ' . var_export($second, true));
    same(1, $b->asked(2), 'and really asked them');
    same('verify', $b->stage(), 'still not fired before B\'s own fire time');
    same(0, $b->firedTo(2) + $b->firedTo(3), 'nothing sent early');
});

/** Both nodes in verify, then A is cut off while B asks the second person itself. Returns the first ask time. */
function bAsksSecond(TW $a, TW $b): int
{
    $due = (int) $a->engine->state()['next_due_at'];
    both($a, $b, $due + 73 * H, syncAB($a, $b));
    $v = (int) $a->cycle()['verify']['asked_at'];
    for ($t = Clock::now(); $t <= $v + 7 * H; $t += 900) {
        Clock::set($t);
        $b->engine->tick();
    }
    same(1, $b->asked(2), 'B asked the second person');
    same(0, $a->asked(2), 'A did not');
    return $v;
}

test('"fine" given on B closes the cycle on A after sync', function (): void {
    [$a, $b] = pairOf(3);
    bAsksSecond($a, $b);
    same('fine', $b->engine->nokAnswer($b->token(2), 'fine'), 'the second person answers on the link B sent');
    same(null, $b->cycle(), 'B closed');
    $a->hear($b);
    same(null, $a->cycle(), 'A closed after sync');
    both($a, $b, Clock::now() + 5 * 24 * H, syncAB($a, $b));
    same(0, $a->asked(3) + $b->asked(3), 'the third was never asked');
    same(0, $a->firedTo(1) + $b->firedTo(1), 'nothing fired');
});

test('a "fine" on A: B tells the people B asked that it was a false alarm, once', function (): void {
    [$a, $b] = pairOf(3);
    bAsksSecond($a, $b);
    same('fine', $a->engine->nokAnswer($a->token(1), 'fine'), 'the first person answers on A');
    $b->hear($a);
    same(null, $b->cycle(), 'B closed');
    both($a, $b, Clock::now() + 2 * H, syncAB($a, $b));
    same(1, $b->allClear(2), 'B told the person it asked');
    same(0, $a->allClear(2), 'A did not tell them a second time');
    same(0, $a->allClear(1) + $b->allClear(1), 'the one who answered is not told');
    $text = '';
    foreach ($b->mail->sent as $m) {
        if (in_array('p2@example.test', $m['to'], true) && str_contains($m['subject'], 'False alarm')) {
            $text = $m['text'];
        }
    }
    check(str_contains($text, 'P1 has confirmed that Sam Rivers is all right'), 'names who answered: ' . $text);
});

test('a peer\'s "fine" heard in the same sync as another peer\'s firing closes the cycle first', function (): void {
    [$a, $b] = pairOf(1);
    $due = (int) $a->engine->state()['next_due_at'];
    both($a, $b, $due + 73 * H, syncAB($a, $b));
    $v = (int) $a->cycle()['verify']['asked_at'];
    Clock::set($v + 71 * H);
    same('fine', $a->engine->nokAnswer($a->token(1), 'fine'), 'the next of kin answers on A');
    Clock::set($v + 73 * H);
    $c = ['node' => 'n_cccccccc', 'at' => Clock::now(), 'last_checkin_at' => null, 'last_checkin_via' => null, 'armed' => true, 'released' => true,
        'cycle' => ['key' => $due, 'stage' => 'fired', 'steps' => ['checkin' => $due, 'verify' => $v, 'fire' => $v + 72 * H],
            'fired_at' => $v + 72 * H, 'fired_reason' => 'timeout', 'nok' => null]];
    $b->coord->recordPeer('n_cccccccc', $c, Clock::now());
    $b->coord->recordPeer(IA, $a->engine->nodeState(IA), Clock::now());
    $b->engine->applyRemote();
    same(null, $b->cycle(), 'B closed on the "fine"');
    $b->engine->tick();
    same(0, $b->firedTo(1), 'B sent nothing');
});

test('federation scenario: nodes holding different chains both wait for the longest one', function (): void {
    $ranks = [IA => 0, IB => 1];
    $a = (new TW(4, ['mode' => 'sequential', 'interval' => 1440, 'window' => 24], $ranks, IA))->named(IA);
    $b = (new TW(4, [], $ranks, IB, $a))->named(IB);
    $b->engine->contacts()->setTrusted($b->ids[2], false);
    $b->engine->contacts()->setTrusted($b->ids[3], false);
    $due = (int) $a->engine->state()['next_due_at'];
    both($a, $b, $due + 73 * H, syncAB($a, $b));
    $v = (int) $a->cycle()['verify']['asked_at'];
    same($v + 96 * H, Engine::plan($a->cycle(), null)['fire']['at'], 'A: three days of asks plus the window');
    both($a, $b, $v + 95 * H, syncAB($a, $b));
    same('verify', $b->stage(), 'B, with only two people of its own, does not fire at 72 hours');
    same($v + 96 * H, Engine::plan($b->cycle(), null)['fire']['at'], 'B waits as long as A');
    for ($n = 1; $n <= 4; $n++) {
        same(1, $a->asked($n) + $b->asked($n), "P$n asked once across both nodes");
    }
    same(0, $a->firedTo(1) + $b->firedTo(1), 'nothing sent early');
    both($a, $b, $v + 98 * H, syncAB($a, $b));
    same(['fired', 'fired'], [$a->stage(), $b->stage()], 'both fire after the longest chain');
});

test('a peer with a fast clock cannot pull the later asks or the fire time forward', function (): void {
    [$a, $b] = pairOf(3, ['mode' => 'sequential', 'interval' => 1440, 'window' => 72]);
    $due = (int) $a->engine->state()['next_due_at'];
    both($a, $b, $due + 73 * H, syncAB($a, $b));
    $v = (int) $b->cycle()['verify']['asked_at'];
    $claim = function (int $now) use ($a, $b, $v): void {
        Clock::set($now);
        $st = $a->engine->nodeState(IA);
        $st['cycle']['steps']['ask:' . $b->ids[1]] = $v + 26 * H; // A's clock is two hours ahead
        $b->coord->recordPeer(IA, $st, $now);
        $b->engine->tick();
    };
    $claim($v + 2 * H);
    $plan = Engine::plan($b->cycle(), null);
    same(false, $plan['ask:' . $b->ids[1]]['done'], 'not taken in a day early');
    same($v + 48 * H, $plan['ask:' . $b->ids[2]]['at'], 'the third still on B\'s own schedule');
    same($v + 120 * H, $plan['fire']['at'], 'the fire time unchanged');
    $claim($v + 24 * H);
    same($v + 24 * H, $b->cycle()['verify']['asks'][$b->ids[1]]['asked_at'] ?? null, 'taken in at B\'s own time, not earlier');
    same($v + 120 * H, Engine::plan($b->cycle(), null)['fire']['at'], 'the fire time still unchanged');
});

finish();
