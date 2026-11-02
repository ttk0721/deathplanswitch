<?php
/** The pages for the chain of trusted people: contacts, settings, the dashboard and the answer page. */
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Clock, Contacts, Store, Tokens};
require __DIR__ . '/lib.php';

const PROOT = __DIR__ . '/..';

function site(bool $armed = true, ?array $cycle = null): array
{
    $dir = sys_get_temp_dir() . '/dps-tp-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700, true);
    $s = new Store($dir);
    $s->write('config', [
        'installed_at' => 1_700_000_000, 'password_hash' => password_hash('correct horse', PASSWORD_DEFAULT),
        'base_url' => 'https://switch.example.test', 'timezone' => 'UTC', 'owner_name' => 'Ann', 'owner_email' => 'ann@example.test',
        'slot_dow' => 1, 'slot_hour' => 9,
    ]);
    $ids = [];
    $list = [];
    foreach ([['nok', 'Kim'], ['other', 'Lee'], ['other', 'Max']] as $i => [$kind, $name]) {
        $c = Contacts::blank($kind);
        $c['name'] = $name;
        $c['email'] = strtolower($name) . '@example.test';
        $c['status'] = 'confirmed';
        $c['created_at'] = 1000 + $i;
        $ids[$name] = $c['id'];
        $list[] = $c;
    }
    $s->write('contacts', ['contacts' => $list]);
    $s->write('state', ['armed' => $armed, 'next_due_at' => time() + 86400, 'cycle' => $cycle, 'last_tick_at' => time()]);
    return [$dir, $s, $ids];
}

/** A signed-in request in a child process; returns [output, exit code]. */
function req(string $dir, string $page, ?array $post = null, array $get = []): array
{
    $script = sys_get_temp_dir() . '/dps-tp-req-' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($script, '<?php
        $dir = getenv("T_DIR"); putenv("DPS_DATA=" . $dir);
        $_SERVER["SCRIPT_NAME"] = "/index.php";
        $_SERVER["REQUEST_METHOD"] = getenv("T_POST") !== false ? "POST" : "GET";
        $_GET = ["p" => getenv("T_PAGE")] + json_decode(getenv("T_GET"), true);
        session_save_path($dir);
        require getenv("T_ROOT") . "/app/bootstrap.php";
        DPS\Web::session();
        $cfg = DPS\store()->read("config");
        if (getenv("T_PAGE") !== "respond") { $_SESSION["owner"] = true; $_SESSION["pw"] = substr($cfg["password_hash"], -16); }
        $_SESSION["csrf"] = "tok";
        if (getenv("T_POST") !== false) { $_POST = json_decode(getenv("T_POST"), true) + ["csrf" => "tok"]; }
        DPS\Web::page(getenv("T_PAGE"));
    ');
    $env = ['T_DIR' => $dir, 'T_ROOT' => realpath(PROOT), 'T_PAGE' => $page, 'T_GET' => json_encode($get), 'PATH' => getenv('PATH') ?: ''];
    if ($post !== null) {
        $env['T_POST'] = json_encode($post);
    }
    $proc = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    $out = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
    $code = proc_close($proc);
    @unlink($script);
    return [$out, $code];
}

test('the contacts page marks trusted people and reorders them with plain forms', function (): void {
    [$dir, $s, $ids] = site();
    $book = new Contacts($s);
    $book->setTrusted($ids['Lee'], true);
    $book->setTrusted($ids['Max'], true);
    [$html] = req($dir, 'contacts');
    check(str_contains($html, 'Trusted people') && str_contains($html, 'Trusted person 3'), 'the order is shown');
    check(!str_contains($html, 'Fatal') && !str_contains($html, 'Warning'), 'no PHP errors: ' . substr($html, 0, 300));
    req($dir, 'contacts', ['id' => $ids['Max'], 'action' => 'up']);
    same([$ids['Kim'], $ids['Max'], $ids['Lee']], array_column((new Contacts($s))->trusted(), 'id'), 'up button');
    req($dir, 'contacts', ['id' => $ids['Max'], 'action' => 'untrust']);
    same([$ids['Kim'], $ids['Lee']], array_column((new Contacts($s))->trusted(), 'id'), 'removed');
    req($dir, 'contacts', ['id' => $ids['Max'], 'action' => 'trust']);
    same([$ids['Kim'], $ids['Lee'], $ids['Max']], array_column((new Contacts($s))->trusted(), 'id'), 'added to the end');
});

test('settings: saved under the lock, validated, and the example follows the list', function (): void {
    [$dir, $s, $ids] = site();
    $book = new Contacts($s);
    foreach (['Lee', 'Max'] as $n) {
        $book->setTrusted($ids[$n], true);
    }
    [$html] = req($dir, 'settings');
    check(str_contains($html, 'With 3 people, the next person is asked every 6 hours.'), 'example for 3 people: ' . substr($html, strpos($html, 'With ') ?: 0, 100));
    req($dir, 'settings', ['action' => 'trust_save', 'trust_mode' => 'all_at_once', 'trust_interval_kind' => 'fixed', 'trust_interval_minutes' => '90', 'trust_window' => '48']);
    same(['mode' => 'all_at_once', 'interval' => 90, 'window' => 48], $s->read('config')['trust'], 'saved');
    req($dir, 'settings', ['action' => 'trust_save', 'trust_mode' => 'sequential', 'trust_interval_kind' => 'fixed', 'trust_interval_minutes' => '5', 'trust_window' => '48']);
    same(90, $s->read('config')['trust']['interval'], 'an interval below 15 minutes is refused');
    req($dir, 'settings', ['action' => 'trust_save', 'trust_mode' => 'sequential', 'trust_interval_kind' => 'auto', 'trust_interval_minutes' => '60', 'trust_window' => '100']);
    same(48, $s->read('config')['trust']['window'], 'a window above 72 hours is refused');
    req($dir, 'settings', ['action' => 'trust_save', 'trust_mode' => 'sequential', 'trust_interval_kind' => 'auto', 'trust_interval_minutes' => '60', 'trust_window' => '6']);
    same(['mode' => 'sequential', 'interval' => 'auto', 'window' => 6], $s->read('config')['trust'], 'auto and 6 hours');
});

test('the dashboard lists each person with the time they are asked', function (): void {
    [$dir, $s, $ids] = site();
    $book = new Contacts($s);
    foreach (['Lee', 'Max'] as $n) {
        $book->setTrusted($ids[$n], true);
    }
    [$html] = req($dir, 'switch');
    check(!str_contains($html, 'Fatal') && !str_contains($html, 'Warning'), 'no PHP errors: ' . substr($html, 0, 300));
    check(str_contains($html, 'Will be asked') && str_contains($html, 'Lee') && str_contains($html, 'Max'), 'each person with a time');
});

test('the answer page tells a later trusted person apart and does not promise early messages', function (): void {
    [$dir, $s, $ids] = site(true, null);
    $book = new Contacts($s);
    $book->setTrusted($ids['Lee'], true);
    [$token, $record] = Tokens::issue(86400 * 30);
    $now = time();
    $s->write('state', ['armed' => true, 'next_due_at' => $now + 86400, 'last_tick_at' => $now, 'cycle' => [
        'id' => 'x', 'due_at' => $now - 100, 'started_at' => $now - 100, 'stage' => 'verify', 'tokens' => [], 'reminders' => [$now, $now], 'fired_at' => null,
        'fired_reason' => null, 'notified' => [], 'owner_told_fired_at' => null, 'steps' => [],
        'verify' => ['asked_at' => $now - 50, 'reached' => true, 'reminded_at' => null, 'tokens' => [$record + ['contact' => $ids['Lee']]],
            'response' => null, 'responded_at' => null, 'chain' => [$ids['Kim'], $ids['Lee']], 'mode' => 'sequential', 'gap' => 43200, 'window' => 86400,
            'asks' => [$ids['Kim'] => ['asked_at' => $now - 50, 'reached' => true, 'own' => true], $ids['Lee'] => ['asked_at' => $now - 10, 'reached' => true, 'own' => true]]],
    ]]);
    [$html] = req($dir, 'respond', null, ['t' => $token, 'a' => 'fine']);
    check(str_contains($html, 'one of the people they trust'), 'the wording for a later person: ' . substr($html, 0, 400));
    check(!str_contains($html, 'sent their instructions straight away'), 'no promise of instant messages');
    [$out] = req($dir, 'respond', ['t' => $token, 'answer' => 'wrong']);
    check(stripos($out, 'nobody is sent anything early') !== false, 'after "wrong" the page says nothing is sent early');
    $cycle = $s->read('state')['cycle'];
    same('verify', $cycle['stage'], 'still waiting');
});

/** A cycle in verify over $chain (names), each with a live link; returns [dir, store, ids, tokens by name]. */
function verifySite(array $chain, array $pending = []): array
{
    [$dir, $s, $ids] = site(true, null);
    $book = new Contacts($s);
    foreach (['Lee', 'Max'] as $n) {
        $book->setTrusted($ids[$n], true);
    }
    foreach ($pending as $n) {
        $c = $book->find($ids[$n]);
        $c['status'] = 'pending';
        $book->save($c);
    }
    $now = time();
    $tokens = [];
    $records = [];
    $asks = [];
    foreach ($chain as $i => $n) {
        [$tokens[$n], $record] = Tokens::issue(86400 * 30);
        $records[] = $record + ['contact' => $ids[$n]];
        $asks[$ids[$n]] = ['asked_at' => $now - 50 + $i, 'reached' => true, 'own' => true];
    }
    $s->write('state', ['armed' => true, 'next_due_at' => $now + 86400, 'last_tick_at' => $now, 'cycle' => [
        'id' => 'x', 'due_at' => $now - 100, 'started_at' => $now - 100, 'stage' => 'verify', 'tokens' => [], 'reminders' => [$now, $now], 'fired_at' => null,
        'fired_reason' => null, 'notified' => [], 'owner_told_fired_at' => null, 'steps' => [],
        'verify' => ['asked_at' => $now - 50, 'reached' => true, 'reminded_at' => null, 'tokens' => $records,
            'response' => null, 'responded_at' => null, 'chain' => array_map(fn ($n) => $ids[$n], $chain), 'mode' => 'all_at_once', 'gap' => 900, 'window' => 86400,
            'asks' => $asks]],
    ]);
    return [$dir, $s, $ids, $tokens];
}

test('the answer page, the next of kin unconfirmed: the person asked first is told nothing is sent early, and nothing is', function (): void {
    [$dir, $s, , $tokens] = verifySite(['Lee', 'Max'], ['Kim']);
    [$html] = req($dir, 'respond', null, ['t' => $tokens['Lee'], 'a' => 'wrong']);
    check(str_contains($html, 'one of the people they trust') && !str_contains($html, 'You are their next of kin'), 'the trusted wording: ' . substr($html, 0, 400));
    check(!str_contains($html, 'straight away'), 'no promise of instant messages');
    check(str_contains($html, 'still asked in turn'), 'the others are still asked');
    [$out] = req($dir, 'respond', ['t' => $tokens['Lee'], 'answer' => 'wrong']);
    same('verify', $s->read('state')['cycle']['stage'], 'the switch did not fire');
    check(str_contains($out, 'nobody is sent anything early') && !str_contains($out, 'have been sent their instructions'), 'the page matches: ' . substr($out, 0, 400));
});

test('the answer page: the next of kin first is told their "wrong" sends the instructions; the last person that nothing is sent early', function (): void {
    [$dir, $s, , $tokens] = verifySite(['Kim', 'Lee', 'Max']);
    [$html] = req($dir, 'respond', null, ['t' => $tokens['Kim'], 'a' => 'wrong']);
    check(str_contains($html, 'You are their next of kin') && str_contains($html, 'straight away'), 'the next of kin wording');
    [$html] = req($dir, 'respond', null, ['t' => $tokens['Max'], 'a' => 'wrong']);
    check(str_contains($html, 'Nobody is sent anything early. If Ann does not check in') && !str_contains($html, 'straight away'), 'the last person: ' . substr($html, 0, 400));
    [$out] = req($dir, 'respond', ['t' => $tokens['Max'], 'answer' => 'fine']);
    check(str_contains($out, 'Nobody else will be asked. The others who were asked are told it was a false alarm'), 'a "fine" after others were asked: ' . substr($out, 0, 400));
    same(null, $s->read('state')['cycle'], 'closed');
});

finish();
