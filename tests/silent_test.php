<?php
/** Contacts not told in advance: no invitation, no consent needed, and their first message is the one sent when the switch fires. */
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Clock, Contacts, Email, Engine, Messages, Outbox, Store, Transport, Vault};
require __DIR__ . '/lib.php';

const H = 3600;
const PROOT = __DIR__ . '/..';

final class SMail implements Transport
{
    public array $sent = [];
    public function send(array $smtp, array $to, string $fromName, string $replyTo, Email $email): void
    {
        $this->sent[] = ['at' => Clock::now(), 'to' => $to, 'subject' => $email->subject, 'text' => $email->text()];
    }
}

final class SWorld
{
    public string $dir;
    public Store $store;
    public SMail $mail;
    public Engine $engine;

    public function __construct()
    {
        $this->dir = sys_get_temp_dir() . '/dps-sil-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
        Clock::set((new \DateTimeImmutable('2026-10-07 13:00', new \DateTimeZone('Australia/Sydney')))->getTimestamp());
        $this->store = new Store($this->dir);
        $this->mail = new SMail();
        $this->engine = new Engine($this->store, new Outbox($this->store, $this->mail), fn (): array => ['result' => 'protected', 'detail' => 't', 'checked_at' => Clock::now()]);
        $this->store->write('config', ['owner_name' => 'Sam Rivers', 'owner_email' => 'owner@example.test', 'backup_email' => '',
            'timezone' => 'Australia/Sydney', 'slot_dow' => 1, 'slot_hour' => 9, 'base_url' => 'https://switch.example.test',
            'smtp' => ['from_email' => 'switch@example.test', 'host' => '127.0.0.1', 'port' => 1],
            'password_hash' => password_hash('pw', PASSWORD_DEFAULT), 'installed_at' => Clock::now()]);
    }

    public function contact(string $kind, string $name, string $email, bool $confirmed, bool $silent = false): array
    {
        $c = Contacts::blank($kind);
        $c['name'] = $name;
        $c['email'] = $email;
        $c['silent'] = $silent;
        $c['message']['body'] = preg_replace(Messages::MARKER, 'the blue folder', $c['message']['body']);
        if ($confirmed) {
            $c['status'] = 'confirmed';
            $c['confirmed_at'] = $c['reconfirmed_at'] = $c['has_needs_at'] = Clock::now();
        }
        $this->engine->contacts()->save($c);
        return $c;
    }

    public function hours(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            Clock::advance(H);
            $this->engine->tick();
        }
    }

    public function to(string $address): array
    {
        return array_values(array_filter($this->mail->sent, static fn (array $m): bool => in_array($address, $m['to'], true)));
    }

    /** A signed-in request to a page in a child process, as the page tests do. */
    public function page(string $page, ?array $post = null, array $get = []): string
    {
        $script = sys_get_temp_dir() . '/dps-sil-req-' . bin2hex(random_bytes(4)) . '.php';
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
        $env = ['T_DIR' => $this->dir, 'T_ROOT' => realpath(PROOT), 'T_PAGE' => $page, 'T_GET' => json_encode($get), 'PATH' => getenv('PATH') ?: ''];
        if ($post !== null) {
            $env['T_POST'] = json_encode($post);
        }
        $proc = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $out = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        proc_close($proc);
        @unlink($script);
        return $out;
    }

    public function find(string $id): array
    {
        return (array) (new Contacts($this->store))->find($id);
    }

    /** History lines about email: an invitation that was tried leaves one, sent or not. */
    public function mailEvents(): array
    {
        return array_values(array_filter($this->store->history(), static fn (array $r): bool => str_starts_with((string) ($r['event'] ?? ''), 'mail.')));
    }
}

function post(array $c, array $more = []): array
{
    return $more + ['action' => 'save', 'name' => $c['name'], 'email' => $c['email'], 'phone' => '', 'role_label' => $c['role_label'],
        'subject' => $c['message']['subject'], 'body' => $c['message']['body']];
}

/** Sam's "virtual" world: a confirmed next of kin and a friend who is not told in advance. */
function armedWithSilent(): array
{
    $w = new SWorld();
    $w->contact('nok', 'Kim', 'kin@example.test', true);
    $friend = $w->contact('other', 'Ola', 'ola@example.test', false, true);
    $w->engine->tick();
    return [$w, $friend];
}

test('a silent contact is never sent an invitation, from the engine or the page', function (): void {
    [$w, $friend] = armedWithSilent();
    same(false, $w->engine->sendConfirmation($friend['id']), 'the engine refuses');
    same([], $w->to('ola@example.test'), 'no email');
    $c = Contacts::blank('other');
    $c['name'] = 'Max';
    $c['email'] = 'max@example.test';
    $html = $w->page('contact', post($c, ['silent' => '1', 'send_now' => '1']), ['kind' => 'other']);
    check(!str_contains($html, 'Fatal') && !str_contains($html, 'Warning:'), 'no PHP errors: ' . substr($html, 0, 300));
    $max = array_values(array_filter((new Contacts($w->store))->all(), static fn (array $x): bool => $x['email'] === 'max@example.test'))[0] ?? null;
    check($max !== null, 'saved');
    same(true, $max['silent'], 'not to be told');
    same(null, $max['confirm_sent_at'], 'no invitation');
    same([], $w->mailEvents(), 'no email was even tried');
    $w->page('contact', ['action' => 'send'], ['id' => $max['id']]);
    same([], $w->mailEvents(), 'the "send" button does nothing for them either');
});

test('the switch arms without the silent contact\'s consent, and the checklist does not wait for them', function (): void {
    [$w, $friend] = armedWithSilent();
    same([], $w->engine->arm(), 'armed');
    $hints = implode(' ', array_column($w->engine->checklist(), 'hint'));
    check(!str_contains($hints, 'Ola'), 'no checklist item names them: ' . $hints);
    $all = array_column($w->engine->checklist(), 'ok', 'key');
    same(true, $all['messages'], 'messages');
    same(true, $all['needs'], 'needs: nothing to hand over in advance');
});

test('a silent technical helper counts as the optional technical helper', function (): void {
    $w = new SWorld();
    $w->contact('nok', 'Kim', 'kin@example.test', true);
    $w->contact('technical', 'Tao', 'tech@example.test', false, true);
    same(true, array_column($w->engine->checklist(), 'ok', 'key')['technical'], 'counted');
});

test('their message must still be finished before the switch arms', function (): void {
    $w = new SWorld();
    $w->contact('nok', 'Kim', 'kin@example.test', true);
    $c = Contacts::blank('other');
    $c['name'] = 'Ola';
    $c['email'] = 'ola@example.test';
    $c['silent'] = true;
    $w->engine->contacts()->save($c);
    $w->engine->tick();
    check($w->engine->arm() !== [], 'refused while the starter text is there');
});

test('"has not confirmed, so will not be contacted" is not shown for a silent contact', function (): void {
    [$w] = armedWithSilent();
    $w->contact('other', 'Pat', 'pat@example.test', false);
    $texts = implode(' | ', array_column($w->engine->warnings(), 'text'));
    check(!str_contains($texts, 'Ola'), 'nothing about the silent contact: ' . $texts);
    check(str_contains($texts, 'Pat has not confirmed, so will not be contacted.'), 'the ordinary contact is still warned about');
});

test('the silent contact gets their message when the switch fires, and it is the first email they get', function (): void {
    [$w] = armedWithSilent();
    same([], $w->engine->arm(), 'armed');
    $w->hours(24 * 14);
    same('fired', $w->engine->state()['cycle']['stage'] ?? null, 'fired');
    $got = $w->to('ola@example.test');
    same(1, count($got), 'one email');
    same('Sam Rivers has stopped checking in', $got[0]['subject'], 'their own message');
    check(str_contains($got[0]['text'], 'the blue folder'), 'with their text');
    check(isset($w->engine->state()['cycle']['notified'][array_values(array_filter((new Contacts($w->store))->all(), fn ($c) => $c['name'] === 'Ola'))[0]['id']]), 'marked as notified');
    $kinFired = array_values(array_filter($w->to('kin@example.test'), static fn (array $m): bool => str_contains($m['subject'], 'what happens now')));
    check(str_contains($kinFired[0]['text'] ?? '', 'Ola'), 'the next of kin is told who else was written to');
});

test('a silent contact is never asked to reconfirm once a year', function (): void {
    $w = new SWorld();
    $w->contact('nok', 'Kim', 'kin@example.test', true);
    $w->contact('other', 'Ola', 'ola@example.test', true, true);
    $w->engine->tick();
    same([], $w->engine->arm(), 'armed');
    for ($d = 0; $d < 400; $d++) {
        Clock::advance(24 * H);
        $w->engine->checkIn('the dashboard');
        $w->engine->tick();
    }
    same([], $w->to('ola@example.test'), 'nothing in a year');
    check(count($w->to('kin@example.test')) >= 1, 'the kin was asked to reconfirm');
});

test('not allowed for the next of kin, a trusted person, or anyone holding vault packages', function (): void {
    $w = new SWorld();
    $kin = $w->contact('nok', 'Kim', 'kin@example.test', true);
    $lee = $w->contact('other', 'Lee', 'lee@example.test', true);
    $box = $w->contact('other', 'Bo', 'bo@example.test', true);
    $book = new Contacts($w->store);
    check(str_contains((string) $book->silentBlocker($kin), 'next of kin'), 'next of kin');
    $book->setTrusted($lee['id'], true);
    check(str_contains((string) $book->silentBlocker($book->find($lee['id'])), 'Trusted people'), 'trusted');
    $w->store->write('vault', ['version' => 1, 'owner' => null, 'recipients' => [['id' => 'r_0123456789abcdef', 'contact_id' => $box['id']]], 'packages' => []]);
    check(str_contains((string) $book->silentBlocker($box), 'vault'), 'vault');
    same(null, $book->silentBlocker($w->contact('other', 'Ola', 'ola@example.test', false)), 'an ordinary contact may be');

    // The server refuses it, whatever the form sends.
    foreach ([$kin, $book->find($lee['id']), $box] as $c) {
        $html = $w->page('contact', post($c, ['silent' => '1']), ['id' => $c['id']]);
        same(false, Contacts::isSilent($w->find($c['id'])), "{$c['name']}: not changed");
        check(!str_contains($html, 'Fatal'), 'no PHP errors');
    }
    // The page shows the option switched off, with the reason.
    $html = $w->page('contact', null, ['id' => $box['id']]);
    check(preg_match('~<input type="checkbox" id="silent" name="silent" value="1" disabled~', $html) === 1, 'disabled');
    check(str_contains($html, 'They hold vault packages'), 'explained');
});

test('a silent contact cannot later be made trusted or given vault packages: both are refused', function (): void {
    [$w, $friend] = armedWithSilent();
    $book = new Contacts($w->store);
    try {
        $book->setTrusted($friend['id'], true);
        check(false, 'should refuse');
    } catch (\InvalidArgumentException $e) {
        check(str_contains($e->getMessage(), 'Ola is set not to be told in advance'), 'clear error: ' . $e->getMessage());
    }
    same(null, $book->trustedNumber($friend['id']), 'not trusted');
    $html = $w->page('contacts', ['id' => $friend['id'], 'action' => 'trust']);
    same(null, $book->trustedNumber($friend['id']), 'the page refuses too');
    check(!str_contains($html, 'Fatal'), 'no PHP errors');
    try {
        (new Vault($w->store))->saveRecipient(['id' => 'r_0123456789abcdef', 'contact_id' => $friend['id'], 'label' => 'Ola',
            'code' => ['salt' => 'x'], 'owner_wrapped' => ['iv' => 'x', 'ct' => 'x']]);
        check(false, 'should refuse');
    } catch (\InvalidArgumentException $e) {
        check(str_contains($e->getMessage(), 'cannot hold vault packages'), 'clear error: ' . $e->getMessage());
    }
    same([], (new Vault($w->store))->read()['recipients'], 'no recipient saved');
    check(!str_contains($w->page('vault'), $friend['id']), 'not offered on the vault page');
});

/**
 * Start a child process that takes the store lock, runs $code (with $s, the store) after a short wait, and lets go.
 * Returns once the child holds the lock, so whatever the caller does next races the child's change.
 *
 * @return array{0: resource, 1: string} the process and its script, for finishChild()
 */
function holdLock(string $dir, string $code): array
{
    $script = sys_get_temp_dir() . '/dps-sil-lock-' . bin2hex(random_bytes(4)) . '.php';
    $marker = $script . '.held';
    file_put_contents($script, '<?php
        require getenv("T_ROOT") . "/app/bootstrap.php";
        $s = new DPS\Store(getenv("T_DIR"));
        $s->locked(function () use ($s): void {
            touch(getenv("T_MARK"));
            usleep(600000);
            ' . $code . '
        });
    ');
    $proc = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null,
        ['T_DIR' => $dir, 'T_ROOT' => realpath(PROOT), 'T_MARK' => $marker, 'T_ID' => getenv('T_ID') ?: '', 'PATH' => getenv('PATH') ?: '']);
    for ($i = 0; $i < 500 && !is_file($marker); $i++) {
        usleep(10000);
    }
    check(is_file($marker), 'the child holds the lock');
    return [$proc, $script];
}

function finishChild(array $child): void
{
    proc_close($child[0]);
    @unlink($child[1]);
    @unlink($child[1] . '.held');
}

test('finding 8: the silent check runs under the store lock: a change made meanwhile is seen, for the vault and the contact page', function (): void {
    $b64 = static fn (int $n): string => rtrim(strtr(base64_encode(str_repeat('a', $n)), '+/', '-_'), '=');
    // A vault recipient is being added for Ola while Ola is, at the same moment, set not to be told.
    $w = new SWorld();
    $w->contact('nok', 'Kim', 'kin@example.test', true);
    $ola = $w->contact('other', 'Ola', 'ola@example.test', true);
    putenv('T_ID=' . $ola['id']);
    $child = holdLock($w->dir, '$d = $s->read("contacts"); foreach ($d["contacts"] as &$c) { if ($c["id"] === getenv("T_ID")) { $c["silent"] = true; } } unset($c); $s->write("contacts", $d);');
    $refused = null;
    try {
        (new Vault($w->store))->saveRecipient(['id' => 'r_0123456789abcdef', 'contact_id' => $ola['id'], 'label' => 'Ola',
            'code' => ['salt' => $b64(16), 'iv' => $b64(12), 'ct' => $b64(48)], 'password' => null, 'owner_wrapped' => ['iv' => $b64(12), 'ct' => $b64(20)]]);
    } catch (\InvalidArgumentException $e) {
        $refused = $e->getMessage();
    }
    finishChild($child);
    check($refused !== null && str_contains($refused, 'cannot hold vault packages'), 'the vault refuses: ' . var_export($refused, true));
    same([], (new Vault($w->store))->read()['recipients'], 'no recipient saved');

    // The contact page checked Bo could stay untold; meanwhile Bo was given vault packages. The save must see it.
    $w = new SWorld();
    $w->contact('nok', 'Kim', 'kin@example.test', true);
    $bo = $w->contact('other', 'Bo', 'bo@example.test', true);
    $book = new Contacts($w->store);
    same(null, $book->silentBlocker($bo), 'the early check passes');
    putenv('T_ID=' . $bo['id']);
    $child = holdLock($w->dir, '$s->write("vault", ["version" => 1, "owner" => null, "recipients" => [["id" => "r_0123456789abcdef", "contact_id" => getenv("T_ID")]], "packages" => []]);');
    $refused = null;
    try {
        $book->save(['silent' => true] + $bo, true);
    } catch (\InvalidArgumentException $e) {
        $refused = $e->getMessage();
    }
    finishChild($child);
    check($refused !== null && str_contains($refused, 'vault'), 'the save refuses: ' . var_export($refused, true));
    same(false, Contacts::isSilent($w->find($bo['id'])), 'Bo is still told in advance');
    putenv('T_ID');
});

test('turning it on for a confirmed contact tells them nothing; turning it off invites an unconfirmed one', function (): void {
    $w = new SWorld();
    $w->contact('nok', 'Kim', 'kin@example.test', true);
    $done = $w->contact('other', 'Lee', 'lee@example.test', true);
    $w->page('contact', post($done, ['silent' => '1']), ['id' => $done['id']]);
    same(true, Contacts::isSilent($w->find($done['id'])), 'on');
    same('confirmed', $w->find($done['id'])['status'], 'still confirmed');
    same([], $w->mailEvents(), 'nothing sent');

    $quiet = $w->contact('other', 'Ola', 'ola@example.test', false, true);
    $w->page('contact', post($quiet), ['id' => $quiet['id']]);
    same(false, Contacts::isSilent($w->find($quiet['id'])), 'off');
    $tried = array_filter($w->mailEvents(), static fn (array $r): bool => str_contains((string) $r['text'], 'ola@example.test'));
    check($tried !== [], 'the usual invitation was started (the test mail server refuses it)');
});

test('the contact page shows the calm warning for a silent contact only', function (): void {
    [$w, $friend] = armedWithSilent();
    $warning = 'Their address cannot be checked beforehand, so a typo would only come to light after the switch has fired.';
    $html = $w->page('contact', null, ['id' => $friend['id']]);
    check(str_contains($html, $warning), 'shown');
    check(str_contains($html, 'Not told in advance'), 'marked');
    check(preg_match('~id="silent" name="silent" value="1" checked~', $html) === 1, 'ticked');
    check(!str_contains($html, 'Send the confirmation email'), 'no invitation button');
    $kin = (new Contacts($w->store))->nok();
    check(!str_contains($w->page('contact', null, ['id' => $kin['id']]), $warning), 'not for others');
    $dash = $w->page('switch');
    check(str_contains($dash, 'Not told in advance') && !str_contains($dash, 'Ola has not confirmed'), 'the dashboard lists them calmly');
});

finish();
