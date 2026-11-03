<?php
/** Group channels: what is accepted, how a long text is split, and that it is sent once, only when the switch fires. */
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Channels, Clock, Cluster, Contacts, Coordinator, Email, Engine, Groups, Messages, Notice, Notifier, Outbox, Store, Transport};
require __DIR__ . '/lib.php';

const H = 3600;
const GA = 'n_aaaaaaaa';
const GB = 'n_bbbbbbbb';

final class GMail implements Transport
{
    public array $sent = [];
    public $failWhen = null;
    public function send(array $smtp, array $to, string $fromName, string $replyTo, Email $email): void
    {
        if ($this->failWhen !== null && ($this->failWhen)($to)) {
            throw new \RuntimeException('smtp down');
        }
        $this->sent[] = ['at' => Clock::now(), 'to' => $to, 'subject' => $email->subject, 'text' => $email->text()];
    }
}

/** Records what reaches each channel; $fail decides, per call, whether it fails. */
final class GPing implements Notifier
{
    public array $got = [];
    public int $calls = 0;
    /** @var null|callable(array, Notice): bool */
    public $fail = null;
    public function notify(array $channel, Notice $notice, string $event): void
    {
        $this->calls++;
        if ($this->fail !== null && ($this->fail)($channel, $notice)) {
            throw new \RuntimeException($channel['label'] . ': HTTP 500');
        }
        $this->got[] = ['id' => $channel['id'], 'label' => $channel['label'], 'title' => $notice->title, 'body' => $notice->body, 'event' => $event, 'at' => Clock::now()];
    }
    /** @return list<string> bodies sent to one group's channel, in order */
    public function bodies(array $group): array
    {
        return array_values(array_column(array_filter($this->got, static fn (array $g): bool => $g['id'] === $group['channel']['id']), 'body'));
    }
}

function gnode(string $id, int $rank): array
{
    return ['id' => $id, 'name' => 'N ' . $id, 'url' => 'https://a.example.org',
        'sign_pk' => rtrim(strtr(base64_encode(str_repeat('a', 32)), '+/', '-_'), '='),
        'share_pk' => 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE', 'rank' => $rank];
}

/** One install, armed, with a next of kin, a technical helper and one group channel. */
final class GW
{
    public Store $store;
    public GMail $mail;
    public GPing $ping;
    public Outbox $outbox;
    public Engine $engine;
    public Coordinator $coord;
    public array $group;

    /** @param array<string,int>|null $ranks a cluster, or null for a single node */
    public function __construct(string $text, string $url = 'discord://111/TOKENSECRET', ?array $ranks = null, string $id = GA, ?array $group = null)
    {
        $dir = sys_get_temp_dir() . '/dps-grp-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        Clock::set((new \DateTimeImmutable('2026-10-07 13:00', new \DateTimeZone('Australia/Sydney')))->getTimestamp());
        $this->store = new Store($dir);
        $this->coord = new Coordinator($this->store, $id);
        if ($ranks !== null) {
            $nodes = [];
            foreach ($ranks as $nid => $r) {
                $nodes[] = gnode($nid, $r);
            }
            Cluster::save($this->store, ['k' => 1, 'primary' => null, 'isolation_hours' => 48, 'step_delay_min' => 30, 'nodes' => $nodes]);
        }
        $this->mail = new GMail();
        $this->ping = new GPing();
        $this->outbox = new Outbox($this->store, $this->mail, $this->ping);
        $this->outbox->partPause = 0.0;
        $this->engine = new Engine($this->store, $this->outbox, fn (): array => ['result' => 'protected', 'detail' => 't', 'checked_at' => Clock::now()], $ranks === null ? null : $this->coord);
        $this->store->write('config', ['owner_name' => 'Sam Rivers', 'owner_email' => 'owner@example.test', 'backup_email' => '',
            'timezone' => 'Australia/Sydney', 'slot_dow' => 1, 'slot_hour' => 9, 'base_url' => 'https://switch.example.test',
            'smtp' => ['from_email' => 'switch@example.test'], 'installed_at' => Clock::now()]);
        foreach ([['nok', 'Kim', 'kin@example.test'], ['technical', 'Tao', 'tech@example.test']] as [$k, $n, $e]) {
            $c = Contacts::blank($k);
            $c['name'] = $n; $c['email'] = $e; $c['status'] = 'confirmed';
            $c['confirmed_at'] = $c['reconfirmed_at'] = $c['has_needs_at'] = Clock::now();
            $c['message']['body'] = preg_replace(Messages::MARKER, 'the blue folder', $c['message']['body']);
            $this->engine->contacts()->save($c);
        }
        if ($group === null) {
            [$group, $errors] = Groups::fromInput(null, 'Work – Slack', 'apprise', $url, $text);
            if ($group === null) {
                throw new \RuntimeException(implode(' ', $errors));
            }
        }
        $this->group = $group;
        (new Groups($this->store))->save($group);
        $this->engine->tick();
        if ($errors = $this->engine->arm()) {
            throw new \RuntimeException(implode('; ', $errors));
        }
    }

    public function hours(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            Clock::advance(H);
            $this->engine->tick();
        }
    }

    public function stage(): ?string
    {
        return $this->engine->state()['cycle']['stage'] ?? null;
    }

    /** Run hourly until the cycle reaches $stage, at most $max hours. */
    public function untilStage(string $stage, int $max = 24 * 14): void
    {
        for ($i = 0; $i < $max && $this->stage() !== $stage; $i++) {
            $this->hours(1);
        }
        check($this->stage() === $stage, "reached $stage");
    }

    public function events(string $event): array
    {
        return array_values(array_filter($this->store->history(), static fn (array $r): bool => ($r['event'] ?? '') === $event));
    }

    /** Hear the other node's state, as a sync would. */
    public function hear(GW $from, string $fromId): void
    {
        $this->coord->recordPeer($fromId, $from->engine->nodeState($fromId), Clock::now());
        $this->engine->applyRemote();
    }
}

/** Paragraphs of whole sentences, about $chars characters in all. */
function prose(int $chars, int $paragraph = 600): string
{
    $out = '';
    $since = 0;
    $n = 0;
    while (mb_strlen($out) < $chars) {
        $sentence = 'Sentence number ' . (++$n) . ' tells the group where the shared notes are kept. ';
        $out .= $sentence;
        $since += mb_strlen($sentence);
        if ($since >= $paragraph) {
            $out = rtrim($out) . "\n\n";
            $since = 0;
        }
    }
    return trim(mb_substr($out, 0, $chars));
}

// ---------------------------------------------------------------- accepted addresses

test('a group channel refuses what contact channels refuse: generic forwarders, a second address, junk', function (): void {
    foreach (['json://internal.host/x', 'JSONS://internal.host/x', 'xml://internal.host/x', 'xmls://x/y', 'form://internal.host/x', 'forms://x/y',
        'https://hooks.slack.com/x', 'http://10.0.0.1/x', 'discord://a/b,json://internal.host/x', 'discord://a/b;form://x', 'discord://a b', 'not a url'] as $url) {
        [$g, $errors] = Groups::fromInput(null, 'Team', 'apprise', $url, 'Hello');
        same(null, $g, "refused $url");
        check($errors !== [] && str_contains($errors[0], 'not accepted'), "explained $url");
    }
    foreach (['ftp://x.test/in', 'file:///etc/passwd', 'gopher://10.0.0.1/'] as $url) {
        same(null, Groups::fromInput(null, 'Team', 'webhook', $url, 'Hello')[0], "webhook refused $url");
    }
    same(null, Groups::fromInput(null, 'Team', 'pigeon', 'discord://1/2', 'Hello')[0], 'unknown kind');
});

test('private addresses get the same answer as for a contact channel', function (): void {
    // Contact channels accept a webhook on the owner's own network (self-hosted receivers); a group channel must not differ.
    foreach (['http://127.0.0.1/hook', 'http://192.168.1.5:8080/in', 'http://10.0.0.1/x', 'http://[::1]/x', 'https://hooks.example.test/in'] as $url) {
        $contact = Channels::normalize(['type' => 'webhook', 'url' => $url]);
        $group = Groups::fromInput(null, 'Team', 'webhook', $url, 'Hello')[0];
        same($contact === null, $group === null, "same verdict for $url");
    }
});

test('a group channel needs a name and a text of bounded length, and a webhook gets its own secret', function (): void {
    [$g, $errors] = Groups::fromInput(null, ' ', 'apprise', 'discord://1/2', '');
    same(null, $g, 'refused');
    same(2, count($errors), 'name and text');
    same(null, Groups::fromInput(null, 'Team', 'apprise', 'discord://1/2', str_repeat('a', Groups::TEXT_MAX + 1))[0], 'too long');
    same(null, Groups::fromInput(null, str_repeat('n', Groups::NAME_MAX + 1), 'apprise', 'discord://1/2', 'Hi')[0], 'name too long');
    [$g] = Groups::fromInput(null, "Work\n – Slack", 'webhook', 'https://hooks.example.test/in', "Line one\r\nLine two");
    check(str_starts_with($g['id'], 'g_') && preg_match('/^[0-9a-f]{32}$/', (string) $g['channel']['secret']) === 1, 'id and secret');
    same('Work  – Slack', $g['name'], 'no line break in the name');
    same('Work  – Slack', $g['channel']['label'], 'the channel is labelled with the name');
    same("Line one\nLine two", $g['text'], 'line ends made plain');
    // An edit with no address keeps the saved one, and with it the secret.
    [$edited] = Groups::fromInput($g, 'Work', 'webhook', '', 'New text');
    same($g['channel']['secret'], $edited['channel']['secret'], 'secret kept');
    same($g['channel']['url'], $edited['channel']['url'], 'address kept');
    same($g['id'], $edited['id'], 'same group');
    [$moved] = Groups::fromInput($g, 'Work', 'apprise', 'slack://a/b/c', 'New text');
    same(null, $moved['channel']['secret'], 'a new Apprise address has no secret');
});

// ---------------------------------------------------------------- splitting

test('each platform has its limit', function (): void {
    same(2000, Groups::limit(['type' => 'apprise', 'url' => 'discord://1/2']), 'discord');
    same(4096, Groups::limit(['type' => 'apprise', 'url' => 'tgram://bot/chat']), 'telegram');
    same(40000, Groups::limit(['type' => 'apprise', 'url' => 'slack://a/b/c']), 'slack');
    same(4000, Groups::limit(['type' => 'apprise', 'url' => 'ntfy://topic']), 'others');
    same(4000, Groups::limit(['type' => 'webhook', 'url' => 'https://x.test/in']), 'webhook');
});

test('a short text is one message with no marker', function (): void {
    same(['Hello, all.'], Groups::split("  Hello, all.\r\n", 2000), 'as written');
    $exact = str_repeat('x', 2000);
    same([$exact], Groups::split($exact, 2000), 'exactly at the limit');
});

test('5000 characters to Discord: three ordered parts, each within the limit, with markers', function (): void {
    $text = prose(5000);
    $parts = Groups::split($text, 2000);
    same(3, count($parts), 'three parts');
    foreach ($parts as $i => $part) {
        check(mb_strlen($part) <= 2000, 'part ' . ($i + 1) . ' within the limit: ' . mb_strlen($part));
        check(str_ends_with($part, ' (' . ($i + 1) . '/3)'), 'marker on part ' . ($i + 1));
    }
    $joined = implode(' ', array_map(static fn (string $p): string => preg_replace('/ \(\d\/3\)$/', '', $p), $parts));
    same(preg_replace('/\s+/', ' ', $text), preg_replace('/\s+/', ' ', $joined), 'nothing lost, nothing reordered');
});

test('a paragraph break is preferred over a sentence or a word', function (): void {
    $first = prose(1500, 100000);
    $second = prose(1200, 100000);
    $parts = Groups::split($first . "\n\n" . $second, 2000);
    same(2, count($parts), 'two parts');
    same($first . ' (1/2)', $parts[0], 'the first part ends at the paragraph');
    // Without a paragraph break, the cut is after a sentence.
    $parts = Groups::split(prose(3000, 100000), 2000);
    check(preg_match('/\. \(1\/2\)$/', $parts[0]) === 1, 'ends after a sentence: ' . mb_substr($parts[0], -30));
    // Without either, between words; a single word longer than a part is cut as a last resort.
    $parts = Groups::split(trim(str_repeat('word ', 900)), 2000);
    check(preg_match('/word \(1\/3\)$/', $parts[0]) === 1 && mb_strlen($parts[0]) <= 2000, 'between words');
    $parts = Groups::split(str_repeat('ż', 4500), 2000);
    same(3, count($parts), 'a long word is cut');
    foreach ($parts as $p) {
        check(mb_strlen($p) <= 2000, 'counted in characters, not bytes');
    }
});

test('the marker grows with the number of parts and still fits', function (): void {
    $parts = Groups::split(trim(str_repeat('abc ', 3000)), 100);
    check(count($parts) >= 100, 'a hundred or more parts');
    foreach ($parts as $i => $p) {
        check(mb_strlen($p) <= 100, 'part ' . ($i + 1) . ' fits');
    }
    check(str_ends_with(end($parts), '/' . count($parts) . ')'), 'numbered to the end');
});

// ---------------------------------------------------------------- when it is sent

test('a group channel is sent nothing at reminders or the kin\'s question, then once at firing, with the full text', function (): void {
    $text = "Hello all. If you are reading this, {owner_name} has not checked in for a while.\n\nThe shared drive is in the blue folder.";
    $w = new GW($text);
    $w->untilStage('verify');
    $w->hours(24 * 2);
    same('verify', $w->stage(), 'still waiting for the kin');
    same([], $w->ping->bodies($w->group), 'nothing during reminders and the question');
    $w->untilStage('fired');
    $w->hours(24);
    $bodies = $w->ping->bodies($w->group);
    same([str_replace('{owner_name}', 'Sam Rivers', $text)], $bodies, 'once, the whole text, filled in');
    same('group', $w->ping->got[0]['event'], 'sent as a group message');
    same('', $w->ping->got[0]['title'], 'no title of its own');
    same(1, count($w->events('group.sent')), 'one history line');
    check(str_contains($w->events('group.sent')[0]['text'], 'in 1 part'), 'parts in history: ' . $w->events('group.sent')[0]['text']);
    // The owner turns up: the group channel gets its correction, once, and nothing afterwards.
    $w->engine->checkIn('the dashboard');
    $w->hours(24 * 5);
    same(2, count($w->ping->bodies($w->group)), 'the correction only, and nothing afterwards');
    check(!str_contains((string) json_encode($w->store->history()), 'TOKENSECRET'), 'no address in history');
    check(!str_contains((string) json_encode($w->store->read('mail')), 'TOKENSECRET'), 'no address in the mail state');
    check(!str_contains((string) json_encode($w->engine->nodeState(GA)), 'TOKENSECRET'), 'no address in the state peers read');
});

test('after the kin answers "All fine", nothing is sent to the group channel', function (): void {
    $w = new GW('Hello all.');
    $w->untilStage('verify');
    $text = '';
    foreach ($w->mail->sent as $m) {
        if (in_array('kin@example.test', $m['to'], true)) {
            $text = $m['text'];
        }
    }
    check(preg_match('~respond\.php\?t=([A-Za-z0-9_-]+)&a=fine~', $text, $m) === 1, 'the kin got answer links');
    same('fine', $w->engine->nokAnswer($m[1], 'fine'), 'answered');
    $w->hours(24 * 6);
    same([], $w->ping->bodies($w->group), 'nothing sent');
});

test('a long text goes as numbered parts in order, each once', function (): void {
    $w = new GW(prose(5000));
    $w->untilStage('fired');
    $w->hours(3);
    $bodies = $w->ping->bodies($w->group);
    same(3, count($bodies), 'three messages');
    foreach ($bodies as $i => $b) {
        check(str_ends_with($b, '(' . ($i + 1) . '/3)'), 'in order: part ' . ($i + 1));
    }
    check(str_contains($w->events('group.sent')[0]['text'], 'in 3 parts'), 'history says three parts');
    same(3, count($w->engine->state()['cycle']['groups'][$w->group['id']]['sent']), 'three parts recorded');
});

test('a failure on part 2 resumes at part 2 and never resends part 1', function (): void {
    $w = new GW(prose(5000));
    $failures = 1;
    $w->ping->fail = function (array $channel, Notice $n) use (&$failures): bool {
        if (str_ends_with($n->body, '(2/3)') && $failures > 0) {
            $failures--;
            return true;
        }
        return false;
    };
    $w->untilStage('fired');
    $bodies = $w->ping->bodies($w->group);
    same(1, count($bodies), 'only part 1 got through in the firing run');
    same(1, count($w->events('group.failed')), 'the failure is in the history');
    check(str_contains($w->events('group.failed')[0]['text'], 'part 2 of 3') && str_contains($w->events('group.failed')[0]['text'], 'HTTP 500'), 'which part and why: ' . $w->events('group.failed')[0]['text']);
    $w->hours(1);
    $bodies = $w->ping->bodies($w->group);
    same(3, count($bodies), 'parts 2 and 3 on the next run');
    same(1, count(array_filter($bodies, static fn (string $b): bool => str_ends_with($b, '(1/3)'))), 'part 1 once');
    check(str_ends_with($bodies[1], '(2/3)') && str_ends_with($bodies[2], '(3/3)'), 'in order');
    $w->hours(5);
    same(3, count($w->ping->bodies($w->group)), 'nothing more');
});

test('a group channel that keeps failing never blocks email to the contacts, and is given up after a while', function (): void {
    $w = new GW('Hello all.');
    $w->ping->fail = static fn (): bool => true;
    $w->untilStage('fired');
    foreach (['kin@example.test', 'tech@example.test'] as $a) {
        check(array_filter($w->mail->sent, static fn (array $m): bool => in_array($a, $m['to'], true) && str_contains($m['subject'], 'stopped checking in')) !== [], "$a was sent their message");
    }
    same(2, count($w->engine->state()['cycle']['notified']), 'both notified');
    // Given up by time: 72 hours after the first attempt, however many attempts that took.
    $first = (int) $w->engine->state()['cycle']['groups'][$w->group['id']]['first_at'];
    $w->hours(70);
    same(false, $w->engine->state()['cycle']['groups'][$w->group['id']]['gave_up'], 'still trying after 70 hours');
    $w->hours(10);
    $failed = $w->events('group.failed');
    check(count($failed) > 12, 'more than twelve attempts, each in the history: ' . count($failed));
    check(str_contains($failed[0]['text'], 'No more attempts'), 'the last says it stops');
    same(true, $w->engine->state()['cycle']['groups'][$w->group['id']]['gave_up'], 'given up');
    $lastAt = (int) $failed[0]['at'];
    check($lastAt >= $first + 72 * H && $lastAt < $first + 73 * H, 'given up 72 hours after the first attempt');
    $w->hours(10);
    same(count($failed), count($w->events('group.failed')), 'no attempt after giving up');
    check(array_filter($w->engine->warnings(), static fn (array $x): bool => $x['key'] === 'channels' && str_contains($x['text'], 'Work – Slack')) !== [], 'the dashboard warns');
});

test('a run that has spent its channel time leaves the remaining parts for the next run', function (): void {
    $w = new GW(prose(5000));
    $w->outbox->channelBudget = 0.0;
    $w->untilStage('fired');
    same([], $w->ping->bodies($w->group), 'no time left in this run');
    same([], $w->events('group.failed'), 'not counted as a failure');
    $w->outbox->channelBudget = 120.0;
    $w->hours(1);
    same(3, count($w->ping->bodies($w->group)), 'sent on the next run');
});

test('the parts are paced: a pause before each part after the first', function (): void {
    $w = new GW(prose(5000));
    $pauses = [];
    $w->outbox->partPause = 1.5;
    $w->outbox->pause = function (float $s) use (&$pauses): void { $pauses[] = $s; };
    $w->untilStage('fired');
    same([1.5, 1.5], $pauses, 'a pause before parts 2 and 3');
});

test('two nodes: each part is delivered exactly once, and one node resumes where the other stopped', function (): void {
    $text = prose(5000);
    $ranks = [GA => 0, GB => 1];
    $a = new GW($text, 'discord://111/TOKENSECRET', $ranks, GA);
    $b = new GW($text, 'discord://111/TOKENSECRET', $ranks, GB, $a->group);
    $b->store->write('contacts', $a->store->read('contacts'));
    // Node A cannot get part 2 through at all; node B can.
    $a->ping->fail = static fn (array $c, Notice $n): bool => str_ends_with($n->body, '(2/3)');
    $due = (int) $a->engine->state()['next_due_at'];
    for ($t = Clock::now(); $t <= $due + 8 * 24 * H; $t += 900) {
        Clock::set($t);
        $a->hear($b, GB);
        $b->hear($a, GA);
        $a->engine->tick();
        $b->engine->tick();
    }
    same('fired', $a->stage(), 'A fired');
    same('fired', $b->stage(), 'B fired');
    $all = array_merge($a->ping->bodies($a->group), $b->ping->bodies($b->group));
    foreach ([1, 2, 3] as $n) {
        same(1, count(array_filter($all, static fn (string $x): bool => str_ends_with($x, "($n/3)"))), "part $n delivered once");
    }
    same(1, count($a->ping->bodies($a->group)), 'A sent part 1');
    same(2, count($b->ping->bodies($b->group)), 'B sent parts 2 and 3');
    check(str_ends_with($b->ping->bodies($b->group)[0], '(2/3)'), 'B started at part 2');
    same(3, count($a->engine->state()['cycle']['groups'][$a->group['id']]['sent']), 'A knows all three went');
});

test('two healthy nodes: the parts go once in all', function (): void {
    $ranks = [GA => 0, GB => 1];
    $a = new GW(prose(5000), 'discord://111/TOKENSECRET', $ranks, GA);
    $b = new GW(prose(5000), 'discord://111/TOKENSECRET', $ranks, GB, $a->group);
    $b->store->write('contacts', $a->store->read('contacts'));
    $due = (int) $a->engine->state()['next_due_at'];
    for ($t = Clock::now(); $t <= $due + 8 * 24 * H; $t += 900) {
        Clock::set($t);
        $a->hear($b, GB);
        $b->hear($a, GA);
        $a->engine->tick();
        $b->engine->tick();
    }
    same(3, count($a->ping->bodies($a->group)) + count($b->ping->bodies($b->group)), 'three messages in all');
});

// ---------------------------------------------------------------- review findings

/** The length of $s in UTF-16 code units, worked out here rather than by the code under test. */
function utf16len(string $s): int
{
    return intdiv(strlen(mb_convert_encoding($s, 'UTF-16LE', 'UTF-8')), 2);
}

/** Owner emails about the corrections. */
function correctionMails(GW ...$worlds): array
{
    $out = [];
    foreach ($worlds as $w) {
        foreach ($w->mail->sent as $m) {
            if (in_array('owner@example.test', $m['to'], true) && str_contains($m['subject'], 'group channels and their corrections')) {
                $out[] = $m;
            }
        }
    }
    return $out;
}

test('finding 1: the text is frozen with its SHA-256 at the first attempt; later runs send its parts even when the names change', function (): void {
    $text = "{owner_name} wrote this.\n\n" . prose(5000);
    $w = new GW($text);
    $failures = 1;
    $w->ping->fail = function (array $c, Notice $n) use (&$failures): bool {
        if (str_ends_with($n->body, '(2/3)') && $failures > 0) {
            $failures--;
            return true;
        }
        return false;
    };
    $w->untilStage('fired');
    $frozen = str_replace('{owner_name}', 'Sam Rivers', $text);
    $row = $w->engine->state()['cycle']['groups'][$w->group['id']];
    same(hash('sha256', $frozen), $row['hash'] ?? null, 'the SHA-256 of the frozen text');
    same($frozen, $row['text'] ?? null, 'the frozen text');
    $expected = Groups::split($frozen, 2000, true);
    // A much longer name before the next run would move every seam if the text were filled in again.
    $config = $w->store->read('config');
    $config['owner_name'] = trim(str_repeat('Samantha Riverside-Longname ', 40));
    $w->store->write('config', $config);
    $w->hours(2);
    same($expected, $w->ping->bodies($w->group), 'the frozen parts, in order, each once');
    $h8 = substr((string) $row['hash'], 0, 8);
    foreach ([1, 2, 3] as $n) {
        check(isset($w->engine->state()['cycle']['steps']['group:' . $w->group['id'] . ':' . $h8 . ':' . $n]), "step group:<id>:<hash8>:$n");
    }

    // A shrunken text must not end the delivery early: three parts frozen, three parts sent.
    $long = trim(str_repeat('Samantha Riverside-Longname ', 15));
    $w2 = new GW("{owner_name} wrote this.\n\n" . prose(3700));
    $config = $w2->store->read('config');
    $config['owner_name'] = $long;
    $w2->store->write('config', $config);
    $failures = 1;
    $w2->ping->fail = $w->ping->fail;
    $w2->untilStage('fired');
    same(1, count($w2->ping->bodies($w2->group)), 'part 1 went in the firing run');
    $config['owner_name'] = 'Sam';
    $w2->store->write('config', $config);
    $w2->hours(2);
    $bodies = $w2->ping->bodies($w2->group);
    same(3, count($bodies), 'all three frozen parts');
    check(str_ends_with($bodies[2], '(3/3)'), 'the last part is the third');
    check(str_starts_with($bodies[0], $long), 'filled in once, with the name as it was');
});

test('finding 1: a node adopts the text a peer froze rather than filling in its own', function (): void {
    $ranks = [GA => 0, GB => 1];
    $text = "{owner_name} wrote this.\n\n" . prose(5000);
    $a = new GW($text, 'discord://111/TOKENSECRET', $ranks, GA);
    $b = new GW($text, 'discord://111/TOKENSECRET', $ranks, GB, $a->group);
    $b->store->write('contacts', $a->store->read('contacts'));
    $config = $b->store->read('config');
    // Long enough to move the seams if B filled the text in itself.
    $config['owner_name'] = trim(str_repeat('Someone Else Entirely ', 15));
    $b->store->write('config', $config);
    // A gets part 1 through and then never another.
    $a->ping->fail = static fn (array $c, Notice $n): bool => !str_ends_with($n->body, '(1/3)');
    $due = (int) $a->engine->state()['next_due_at'];
    for ($t = Clock::now(); $t <= $due + 8 * 24 * H; $t += 900) {
        Clock::set($t);
        $a->hear($b, GB);
        $b->hear($a, GA);
        $a->engine->tick();
        $b->engine->tick();
    }
    $expected = Groups::split(str_replace('{owner_name}', 'Sam Rivers', $text), 2000, true);
    same([$expected[0]], $a->ping->bodies($a->group), 'A sent part 1');
    same([$expected[1], $expected[2]], $b->ping->bodies($b->group), 'B sent parts 2 and 3 of A\'s frozen text');
    same($a->engine->state()['cycle']['groups'][$a->group['id']]['hash'], $b->engine->state()['cycle']['groups'][$b->group['id']]['hash'], 'one hash on both nodes');
});

test('finding 2: after a false alarm each group channel that got the message is sent its correction once, and the owner is told', function (): void {
    $w = new GW(prose(5000));
    [$off] = Groups::fromInput(null, 'Quiet – ntfy', 'apprise', 'ntfy://quiet-topic', 'Hello.', false);
    [$broken] = Groups::fromInput(null, 'Broken – Discord', 'apprise', 'discord://333/BROKEN', 'Hello.');
    (new Groups($w->store))->save($off);
    (new Groups($w->store))->save($broken);
    $w->ping->fail = static fn (array $c): bool => $c['label'] === 'Broken – Discord';
    $w->untilStage('fired');
    $w->hours(1);
    same(3, count($w->ping->bodies($w->group)), 'the message went');
    same(1, count($w->ping->bodies($off)), 'and to the second channel');
    $result = $w->engine->checkIn('the dashboard');
    $bodies = $w->ping->bodies($w->group);
    same(4, count($bodies), 'one more message: the correction');
    same('Please disregard the earlier message from Sam Rivers: it was sent by mistake, and Sam Rivers is fine.', $bodies[3], 'the default text, filled in');
    same(1, count($w->ping->bodies($off)), 'a channel with the correction turned off gets nothing more');
    same([['name' => 'Work – Slack', 'status' => 'sent'], ['name' => 'Quiet – ntfy', 'status' => 'off']], $result['groups'], 'the confirmation lists the channels that had the message; not the one that never got it');
    $mails = correctionMails($w);
    same(1, count($mails), 'the owner is emailed');
    check(str_contains($mails[0]['text'], 'Work – Slack: a correction was sent.') && str_contains($mails[0]['text'], 'Quiet – ntfy: no correction')
        && !str_contains($mails[0]['text'], 'Broken'), 'which channels, and that the correction went: ' . $mails[0]['text']);
    same(1, count($w->events('group.corrected')), 'in the history');
    $w->hours(24 * 3);
    same(4, count($w->ping->bodies($w->group)), 'never again');
    same(1, count(correctionMails($w)), 'one email');

    // Checked in before the firing: nothing to correct.
    $v = new GW('Hello all.');
    $v->untilStage('verify');
    same([], $v->engine->checkIn('the dashboard')['groups'], 'nothing listed');
    $v->hours(5);
    same([], $v->ping->bodies($v->group), 'nothing sent');
});

test('finding 2: two nodes send one correction in all, and one email', function (): void {
    $ranks = [GA => 0, GB => 1];
    $a = new GW(prose(5000), 'discord://111/TOKENSECRET', $ranks, GA);
    $b = new GW(prose(5000), 'discord://111/TOKENSECRET', $ranks, GB, $a->group);
    $b->store->write('contacts', $a->store->read('contacts'));
    $due = (int) $a->engine->state()['next_due_at'];
    $run = static function (int $until) use ($a, $b): void {
        for ($t = Clock::now(); $t <= $until; $t += 900) {
            Clock::set($t);
            $a->hear($b, GB);
            $b->hear($a, GA);
            $a->engine->tick();
            $b->engine->tick();
        }
    };
    $run($due + 8 * 24 * H);
    same(3, count($a->ping->bodies($a->group)) + count($b->ping->bodies($b->group)), 'the message went once');
    $b->engine->checkIn('the dashboard');
    $run(Clock::now() + 6 * H);
    $all = array_merge($a->ping->bodies($a->group), $b->ping->bodies($b->group));
    same(1, count(array_filter($all, static fn (string $x): bool => str_starts_with($x, 'Please disregard'))), 'one correction');
    same(1, count(correctionMails($a, $b)), 'one email to the owner');
});

test('finding 3: a group text may only use the owner\'s placeholders; the contacts\' are refused and never filled in', function (): void {
    foreach (['{contacts_list}', '{nok_details}', '{technical_details}', '{your_name}'] as $code) {
        [$g, $errors] = Groups::fromInput(null, 'Team', 'apprise', 'discord://1/2', "Call $code.");
        same(null, $g, "$code refused");
        $said = implode(' ', $errors);
        check(str_contains($said, $code) && str_contains($said, '{owner_name}') && str_contains($said, '{last_checkin_date}'), "$code explained: $said");
        same(null, Groups::fromInput(null, 'Team', 'apprise', 'discord://1/2', 'Hi.', true, "Sorry, $code.")[0], "$code refused in the correction too");
    }
    [$g] = Groups::fromInput(null, 'Team', 'apprise', 'discord://1/2', '{owner_name} last checked in on {last_checkin_date}, {days} days ago.');
    check($g !== null, 'the owner\'s own are accepted');
    // A text saved before the rule is never filled in with them.
    $vars = ['{owner_name}' => 'Sam', '{nok_details}' => 'Kim, kin@example.test, +61 400 000 000', '{contacts_list}' => 'Tao, tech@example.test'];
    same('Sam: {nok_details} {contacts_list}', Groups::message(['text' => '{owner_name}: {nok_details} {contacts_list}'], $vars), 'left as typed');
    same('Sam is fine. {nok_details}', Groups::correction(['correction_text' => '{owner_name} is fine. {nok_details}'], $vars), 'the correction too');
});

test('finding 4: Telegram and Discord are measured in UTF-16 code units, so an emoji counts as two', function (): void {
    $tg = Groups::parts(['text' => str_repeat('😀', 3000), 'channel' => ['type' => 'apprise', 'url' => 'tgram://bot/chat']], []);
    same(2, count($tg), 'Telegram: 3,000 emoji are 6,000 units, two parts');
    foreach ($tg as $p) {
        check(utf16len($p) <= 4096, 'within 4,096 units: ' . utf16len($p));
    }
    $dc = Groups::parts(['text' => str_repeat('😀', 1500), 'channel' => ['type' => 'apprise', 'url' => 'discord://1/2']], []);
    same(2, count($dc), 'Discord: 1,500 emoji are 3,000 units, two parts');
    foreach ($dc as $p) {
        check(utf16len($p) <= 2000, 'within 2,000 units: ' . utf16len($p));
    }
    same(1, count(Groups::parts(['text' => str_repeat('😀', 3000), 'channel' => ['type' => 'apprise', 'url' => 'ntfy://topic']], [])), 'others count characters');
});

test('finding 5: at most 50 group channels; a 51st is refused with an error, on the page too, and an edit still saves', function (): void {
    $w = new GW('Hello all.');
    $book = new Groups($w->store);
    for ($i = 2; $i <= Groups::MAX; $i++) {
        $book->save(Groups::fromInput(null, "Team $i", 'apprise', "ntfy://topic$i", 'Hello.')[0]);
    }
    same(50, count($book->all()), 'fifty');
    try {
        $book->save(Groups::fromInput(null, 'One too many', 'apprise', 'ntfy://more', 'Hello.')[0]);
        check(false, 'should refuse');
    } catch (\InvalidArgumentException $e) {
        same('You can have at most 50 group channels.', $e->getMessage(), 'clear error');
    }
    same(50, count($book->all()), 'still fifty');
    $first = $book->all()[0];
    $book->save(['name' => 'Renamed'] + $first);
    same('Renamed', $book->find($first['id'])['name'], 'an edit saves');
    $config = $w->store->read('config');
    $config['password_hash'] = password_hash('pw', PASSWORD_DEFAULT);
    $w->store->write('config', $config);
    $html = greq($w->store->dir(), 'group', ['action' => 'save', 'name' => 'Another', 'channel_type' => 'apprise', 'channel_url' => 'ntfy://another', 'text' => 'Hi.']);
    check(str_contains($html, 'You can have at most 50 group channels.') && !str_contains($html, 'Fatal'), 'the page shows the error');
    same(50, count($book->all()), 'not saved');
});

test('finding 5: control characters are taken out of a group name', function (): void {
    [$g] = Groups::fromInput(null, "Work\x07\x1b[31m\u{0085}Slack\x7f", 'apprise', 'discord://1/2', 'Hi.');
    same(0, preg_match('/[\x00-\x1f\x7f\x{80}-\x{9f}]/u', $g['name']), 'no control character: ' . json_encode($g['name']));
    check(str_contains($g['name'], 'Work') && str_contains($g['name'], 'Slack'), 'the rest kept');
});

test('finding 6: two nodes with a stale view: the lease holder alone sends; the other takes over only once it stalls', function (): void {
    $ranks = [GA => 0, GB => 1];
    $text = prose(5000);
    $a = new GW($text, 'discord://111/TOKENSECRET', $ranks, GA);
    $b = new GW($text, 'discord://111/TOKENSECRET', $ranks, GB, $a->group);
    $b->store->write('contacts', $a->store->read('contacts'));
    // A: part 1 at once, part 2 only 45 minutes later, part 3 never.
    $t1 = null;
    $a->ping->fail = function (array $c, Notice $n) use (&$t1): bool {
        if (str_ends_with($n->body, '(1/3)')) {
            $t1 = Clock::now();
            return false;
        }
        if (str_ends_with($n->body, '(2/3)')) {
            return $t1 === null || Clock::now() < $t1 + 45 * 60;
        }
        return true;
    };
    $due = (int) $a->engine->state()['next_due_at'];
    $bFirst = null;
    for ($t = Clock::now(); $t <= $due + 8 * 24 * H; $t += 900) {
        Clock::set($t);
        // Once it has fired, A no longer hears B: its view stays stale. B hears A throughout.
        if ($a->stage() !== 'fired') {
            $a->hear($b, GB);
        }
        $b->hear($a, GA);
        $a->engine->tick();
        $b->engine->tick();
        if ($bFirst === null && $b->ping->bodies($b->group) !== []) {
            $bFirst = Clock::now();
        }
    }
    $aSent = $a->ping->bodies($a->group);
    $bSent = $b->ping->bodies($b->group);
    same(2, count($aSent), 'A sent parts 1 and 2');
    check(str_ends_with($aSent[0], '(1/3)') && str_ends_with($aSent[1], '(2/3)'), 'in order');
    same(1, count($bSent), 'B sent only part 3');
    check(str_ends_with($bSent[0], '(3/3)'), 'B resumed at the first part not delivered');
    $t2 = (int) $a->engine->state()['cycle']['groups'][$a->group['id']]['sent'][2];
    check($bFirst !== null && $bFirst >= $t2 + 30 * 60 + 30 * 60, 'B waited 30 minutes plus its rank delay after A\'s last progress: ' . ($bFirst - $t2) . 's');
    $lease = 'group:' . $a->group['id'] . ':' . substr((string) $a->engine->state()['cycle']['groups'][$a->group['id']]['hash'], 0, 8) . ':lease';
    check(isset($a->engine->state()['cycle']['steps'][$lease]), 'A recorded the lease');
    same(GB, $b->engine->state()['cycle']['groups'][$b->group['id']]['lease'], 'B holds it after taking over');
    same(1, count($b->events('group.takeover')), 'the takeover is in the history');
});

test('finding 7: a long word is cut between whole characters: emoji sequences and accents stay together', function (): void {
    $family = "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}";
    foreach ([[$family, 600, 2000, true], [$family, 600, 2000, false], ["e\u{0301}", 2500, 2000, false], ["\u{1F44D}\u{1F3FD}", 1500, 2000, true]] as [$cluster, $n, $limit, $u16]) {
        $parts = Groups::split(str_repeat($cluster, $n), $limit, $u16);
        check(count($parts) >= 2, 'split: ' . count($parts));
        $total = 0;
        foreach ($parts as $i => $p) {
            check(($u16 ? utf16len($p) : mb_strlen($p)) <= $limit, 'part ' . ($i + 1) . ' fits');
            $body = (string) preg_replace('/ \(\d+\/\d+\)$/', '', $p);
            preg_match_all('/\X/u', $body, $m);
            same([], array_values(array_filter($m[0], static fn (string $g): bool => $g !== $cluster)), 'part ' . ($i + 1) . ' holds whole characters only');
            $total += count($m[0]);
        }
        same($n, $total, 'nothing lost');
    }
});

// ---------------------------------------------------------------- the test button

test('the test message is neutral, sent at once, and limited to one a minute per channel', function (): void {
    $w = new GW('Hello all.');
    $other = Groups::fromInput(null, 'Friends – Discord', 'apprise', 'discord://222/OTHER', 'Hi')[0];
    $r = Groups::sendTest($w->store, $w->ping, $w->group);
    same(true, $r['ok'], 'sent');
    same('Test sent to Work – Slack.', $r['message'], 'reported');
    same('test', $w->ping->got[0]['event'], 'a test event');
    check(!str_contains($w->ping->got[0]['body'], 'fired') && str_contains($w->ping->got[0]['body'], 'test message'), 'neutral text');
    Clock::advance(30);
    $r = Groups::sendTest($w->store, $w->ping, $w->group);
    same(false, $r['ok'], 'too soon');
    check(str_contains($r['message'], 'less than a minute ago'), 'explained');
    same(1, $w->ping->calls, 'nothing sent the second time');
    same(true, Groups::sendTest($w->store, $w->ping, $other)['ok'], 'another channel has its own minute');
    Clock::advance(31);
    same(true, Groups::sendTest($w->store, $w->ping, $w->group)['ok'], 'a minute later');
    Clock::advance(61);
    $w->ping->fail = static fn (): bool => true;
    $r = Groups::sendTest($w->store, $w->ping, $w->group);
    same(false, $r['ok'], 'failure reported');
    same('The test failed: Work – Slack: HTTP 500', $r['message'], 'with the reason');
    Clock::advance(10);
    same(false, Groups::sendTest($w->store, $w->ping, $w->group)['ok'], 'a failed test counts against the minute too');
});

// ---------------------------------------------------------------- the pages

/** A signed-in request in a child process; returns the output. */
function greq(string $dir, string $page, ?array $post = null, array $get = []): string
{
    $script = sys_get_temp_dir() . '/dps-grp-req-' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($script, '<?php
        $dir = getenv("T_DIR"); putenv("DPS_DATA=" . $dir);
        $_SERVER["SCRIPT_NAME"] = "/index.php";
        $_SERVER["REQUEST_METHOD"] = getenv("T_POST") !== false ? "POST" : "GET";
        $_GET = ["p" => getenv("T_PAGE")] + json_decode(getenv("T_GET"), true);
        session_save_path($dir);
        require getenv("T_ROOT") . "/app/bootstrap.php";
        DPS\Web::session();
        $cfg = DPS\store()->read("config");
        $_SESSION["owner"] = true; $_SESSION["pw"] = substr($cfg["password_hash"], -16);
        $_SESSION["csrf"] = "tok";
        if (getenv("T_POST") !== false) { $_POST = json_decode(getenv("T_POST"), true) + ["csrf" => "tok"]; }
        DPS\Web::page(getenv("T_PAGE"));
    ');
    $env = ['T_DIR' => $dir, 'T_ROOT' => realpath(__DIR__ . '/..'), 'T_PAGE' => $page, 'T_GET' => json_encode($get), 'PATH' => getenv('PATH') ?: ''];
    if ($post !== null) {
        $env['T_POST'] = json_encode($post);
    }
    $proc = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    $out = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
    proc_close($proc);
    @unlink($script);
    return $out;
}

test('the pages: add, list, edit and test a group channel; the full address is never shown', function (): void {
    $w = new GW('Hello all.');
    $config = $w->store->read('config');
    $config['password_hash'] = password_hash('pw', PASSWORD_DEFAULT);
    $w->store->write('config', $config);
    $dir = $w->store->dir();
    $html = greq($dir, 'group', ['action' => 'save', 'name' => 'Friends – Discord', 'channel_type' => 'webhook', 'channel_url' => 'https://hooks.example.test/in/SECRETPATH', 'text' => 'See you all.']);
    check(!str_contains($html, 'Fatal') && !str_contains($html, 'Warning:'), 'no PHP errors: ' . substr($html, 0, 300));
    $saved = array_values(array_filter((new Groups($w->store))->all(), static fn (array $g): bool => $g['name'] === 'Friends – Discord'));
    same(1, count($saved), 'saved');
    $g = $saved[0];
    $page = greq($dir, 'group', null, ['id' => $g['id']]);
    check(str_contains($page, 'Send a test message') && str_contains($page, 'See you all.'), 'edit page');
    check(!str_contains($page, 'SECRETPATH') && str_contains($page, 'https://hook…'), 'masked address');
    check(str_contains($page, (string) $g['channel']['secret']), 'the signing secret is there to copy');
    // Only the owner's placeholders are offered, and the correction is there to edit.
    foreach (Groups::PLACEHOLDERS as $code) {
        check(str_contains($page, '<code>' . $code . '</code>'), "$code offered");
    }
    foreach (['{contacts_list}', '{nok_details}', '{technical_details}', '{your_name}'] as $code) {
        check(!str_contains($page, '<code>' . $code . '</code>'), "$code not offered");
    }
    check(str_contains($page, 'name="correction" value="1" checked') && str_contains($page, 'Please disregard the earlier message from {owner_name}'), 'the correction, on, with its default text');
    $refused = greq($dir, 'group', ['action' => 'save', 'name' => 'Friends', 'channel_type' => 'webhook', 'channel_url' => '', 'text' => 'Call {nok_details}.'], ['id' => $g['id']]);
    check(str_contains($refused, 'cannot use {nok_details}'), 'a contact placeholder is refused with a clear error');
    same('See you all.', (new Groups($w->store))->find($g['id'])['text'], 'not saved');
    greq($dir, 'group', ['action' => 'save', 'name' => 'Friends – Discord', 'channel_type' => 'webhook', 'channel_url' => '', 'text' => 'See you all.',
        'correction_form' => '1', 'correction_text' => 'All good, {owner_name} is fine.'], ['id' => $g['id']]);
    $g2 = (new Groups($w->store))->find($g['id']);
    same(false, $g2['correction'], 'an unticked box turns the correction off');
    same('All good, {owner_name} is fine.', $g2['correction_text'], 'its own text');
    check(str_contains(greq($dir, 'contacts'), 'Friends – Discord'), 'listed on Contacts');
    $dash = greq($dir, 'switch');
    check(str_contains($dash, 'Friends – Discord') && str_contains($dash, 'Group channel'), 'listed in "Who is told"');
    check(!str_contains($dash, 'SECRETPATH'), 'no address on the dashboard');
    // A refused address keeps the saved one.
    greq($dir, 'group', ['action' => 'save', 'name' => 'Friends', 'channel_type' => 'apprise', 'channel_url' => 'json://internal.host/x', 'text' => 'x'], ['id' => $g['id']]);
    same($g['channel']['url'], (new Groups($w->store))->find($g['id'])['channel']['url'], 'unchanged');
    // The test button tries at once (no notification server here) and is then limited.
    greq($dir, 'group', ['action' => 'test'], ['id' => $g['id']]);
    $first = (int) ($w->store->read('mail')['group_tests'][$g['id']] ?? 0);
    check($first > 0, 'a test was tried');
    greq($dir, 'group', ['action' => 'delete'], ['id' => $g['id']]);
    same(null, (new Groups($w->store))->find($g['id']), 'deleted');
});

test('finding 2: the check-in page lists the group channels that had the message and their correction', function (): void {
    $w = new GW('Hello all.');
    $config = $w->store->read('config');
    $config['password_hash'] = password_hash('pw', PASSWORD_DEFAULT);
    $w->store->write('config', $config);
    $w->untilStage('fired');
    $w->hours(1);
    same(1, count($w->ping->bodies($w->group)), 'the message went');
    $token = null;
    foreach ($w->mail->sent as $m) {
        if (in_array('owner@example.test', $m['to'], true) && preg_match('~checkin\.php\?t=([A-Za-z0-9_-]+)~', $m['text'], $mm)) {
            $token = $mm[1];
        }
    }
    check($token !== null, 'the owner has a check-in link');
    $html = greq($w->store->dir(), 'checkin', ['t' => $token]);
    check(!str_contains($html, 'Fatal'), 'no PHP errors');
    check(str_contains($html, 'these group channels had received your message'), 'the list is shown: ' . substr(strip_tags($html), 0, 400));
    // The child process has no notification server, so the correction is still to go.
    check(str_contains($html, 'Work – Slack: a correction'), 'the channel and its correction');
});

finish();
