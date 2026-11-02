<?php
declare(strict_types=1);
namespace DPS\Tests;

use DPS\{SetupCode, Store};

require __DIR__ . '/lib.php';

const ROOT = __DIR__ . '/..';

function fresh(): string
{
    $dir = sys_get_temp_dir() . '/dps-setup-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700, true);
    return $dir;
}

/** One request to install.php in a child process that shares a session by id. Returns the page. */
function page(string $dir, string &$sid, ?array $post = null, bool $owner = false): string
{
    $script = sys_get_temp_dir() . '/dps-setup-req-' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($script, '<?php
        putenv("DPS_DATA=" . getenv("T_DIR"));
        $_SERVER["SCRIPT_NAME"] = "/install.php";
        $_SERVER["REQUEST_METHOD"] = getenv("T_POST") !== false ? "POST" : "GET";
        session_save_path(getenv("T_DIR") . "/sessions");
        session_id(getenv("T_SID"));
        require getenv("T_ROOT") . "/app/bootstrap.php";
        DPS\Web::session();
        $_SESSION["csrf"] = "tok";
        if (getenv("T_OWNER") === "1") {
            $_SESSION["owner"] = true;
            $_SESSION["pw"] = substr(DPS\store()->read("config")["password_hash"], -16);
        }
        if (getenv("T_POST") !== false) { $_POST = json_decode(getenv("T_POST"), true) + ["csrf" => "tok"]; }
        register_shutdown_function(static function (): void { file_put_contents(getenv("T_DIR") . "/last-sid", session_id()); });
        DPS\Web::page("install");
    ');
    @mkdir($dir . '/sessions', 0700);
    $env = ['T_DIR' => $dir, 'T_ROOT' => realpath(ROOT), 'T_SID' => $sid, 'PATH' => getenv('PATH') ?: ''];
    if ($post !== null) {
        $env['T_POST'] = json_encode($post);
    }
    if ($owner) {
        $env['T_OWNER'] = '1';
    }
    $proc = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    $out = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
    proc_close($proc);
    @unlink($script);
    $sid = (string) @file_get_contents($dir . '/last-sid') ?: $sid; // sign-in regenerates the id
    return $out;
}

test('a code is made fresh each time; only a salted hash is stored, readable only by the owner', function (): void {
    $store = new Store(fresh());
    $code = SetupCode::rotate($store);
    check(preg_match('/^[0-9A-HJKMNP-TV-Z]{4}(-[0-9A-HJKMNP-TV-Z]{4}){4}$/D', (string) $code) === 1, 'format: ' . $code);
    $raw = (string) file_get_contents(SetupCode::path($store));
    check(!str_contains($raw, (string) $code) && !str_contains($raw, str_replace('-', '', (string) $code)), 'the plain code is not on disk');
    check(preg_match('/^v1:[0-9a-f]{32}:[0-9a-f]{64}$/D', trim($raw)) === 1, 'salted sha-256 only');
    same('0600', substr(sprintf('%o', fileperms(SetupCode::path($store))), -4), 'mode');
    $second = SetupCode::rotate($store);
    check($second !== $code, 'a new code each time');
    same(false, SetupCode::matches($store, (string) $code), 'the old code stops working');
    same(true, SetupCode::matches($store, (string) $second), 'the new code works');
    check(SetupCode::generate() !== SetupCode::generate(), 'random');
    check(!is_file(SetupCode::path($store) . '.tmp'), 'no leftovers');
});
test('no code is made once the install is finished', function (): void {
    $store = new Store(fresh());
    $store->write('config', ['installed_at' => 1700000000]);
    same(null, SetupCode::rotate($store), 'installed');
    check(!is_file(SetupCode::path($store)), 'no file');
});
test('the right code is accepted however it is typed; a wrong one is refused', function (): void {
    $store = new Store(fresh());
    $code = (string) SetupCode::rotate($store);
    same(true, SetupCode::attempt($store, $code, 'A'), 'exact');
    same(true, SetupCode::attempt($store, strtolower(str_replace('-', ' ', $code)), 'A'), 'lower case, spaces');
    check(is_string(SetupCode::attempt($store, 'AAAA-BBBB-CCCC-DDDD-EEEE', 'A')), 'wrong refused');
    check(is_string(SetupCode::attempt($store, '', 'A')), 'empty refused');
});
test('a client that fails 10 times is locked, but another client is not, and its right code works', function (): void {
    $store = new Store(fresh());
    $code = (string) SetupCode::rotate($store);
    for ($i = 0; $i < 10; $i++) {
        $r = SetupCode::attempt($store, 'WRONG', '203.0.113.1');
        check(is_string($r) && !str_contains($r, 'Too many'), "attempt $i is a plain refusal");
    }
    $locked = SetupCode::attempt($store, $code, '203.0.113.1');
    check(is_string($locked) && str_contains($locked, 'Too many'), 'client A is locked, even with the right code: ' . var_export($locked, true));
    $r = SetupCode::attempt($store, 'WRONG', '203.0.113.2');
    check(is_string($r) && !str_contains($r, 'Too many'), 'client B is not locked');
    same(true, SetupCode::attempt($store, $code, '203.0.113.2'), 'client B with the right code gets in');
    $locked = SetupCode::attempt($store, $code, '203.0.113.1');
    check(is_string($locked) && str_contains($locked, 'Too many'), 'client A stays locked');
});
test('rotating the code clears every limit', function (): void {
    $store = new Store(fresh());
    SetupCode::rotate($store);
    for ($i = 0; $i < 10; $i++) {
        SetupCode::attempt($store, 'WRONG', '203.0.113.1');
    }
    $code = (string) SetupCode::rotate($store);
    same(true, SetupCode::attempt($store, $code, '203.0.113.1'), 'client A can try again');
});
test('the installer asks for the code and refuses without it', function (): void {
    $dir = fresh();
    $code = (string) SetupCode::rotate(new Store($dir));
    $sid = bin2hex(random_bytes(8));
    $html = page($dir, $sid);
    check(str_contains($html, 'name="setup_code"'), 'code form');
    check(!str_contains($html, 'name="separate"'), 'nothing else before the code');
    $html = page($dir, $sid, ['action' => 'setup_code', 'setup_code' => 'AAAA-BBBB-CCCC-DDDD-EEEE']);
    check(str_contains($html, 'name="setup_code"') && str_contains($html, 'notice-bad'), 'wrong code refused');
    // Skipping the code: posting the later steps does nothing.
    page($dir, $sid, ['action' => 'owner', 'owner_name' => 'X', 'owner_email' => 'x@example.com', 'password' => 'a long password 123', 'password2' => 'a long password 123', 'timezone' => 'UTC', 'base_url' => 'http://x.test']);
    check(!is_file($dir . '/config.json'), 'no config written without the code');
    page($dir, $sid, ['action' => 'setup_code', 'setup_code' => $code]);
    $html = page($dir, $sid);
    check(str_contains($html, 'name="separate"') && !str_contains($html, 'name="setup_code"'), 'steps after the code');
});
test('the code file is deleted when the install finishes', function (): void {
    $dir = fresh();
    $store = new Store($dir);
    SetupCode::rotate($store);
    $store->write('config', ['install_step' => 4, 'installed_at' => null, 'owner_email' => 'a@example.com', 'timezone' => 'UTC',
        'password_hash' => password_hash('correct horse', PASSWORD_DEFAULT)]);
    check(is_file(SetupCode::path($store)), 'present before');
    $sid = bin2hex(random_bytes(8));
    page($dir, $sid, ['action' => 'finish'], true);
    check(!empty($store->read('config')['installed_at']), 'installed');
    check(!is_file(SetupCode::path($store)), 'code file deleted');
});
test('the CLI makes and prints a new code each run, and says nothing is needed once installed', function (): void {
    $dir = fresh();
    $run = static function (array $args) use ($dir): string {
        $proc = proc_open(array_merge([PHP_BINARY, realpath(ROOT) . '/cli/setup-code.php'], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['DPS_DATA' => $dir, 'PATH' => getenv('PATH') ?: '']);
        $out = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        proc_close($proc);
        return $out;
    };
    $store = new Store($dir);
    $grab = static fn (string $out): string => preg_match('/Setup code: ([0-9A-Z]{4}(-[0-9A-Z]{4}){4})/', $out, $m) === 1 ? $m[1] : '';
    $out = $run([]);
    $first = $grab($out);
    check($first !== '' && str_contains($out, '/install.php'), 'prints: ' . $out);
    check(SetupCode::matches($store, $first), 'the printed code is the valid one');
    for ($i = 0; $i < 10; $i++) {
        SetupCode::attempt($store, 'WRONG', '203.0.113.1');
    }
    $second = $grab($run(['--if-needed']));
    check($second !== '' && $second !== $first, 'the container variant makes a fresh code');
    check(!SetupCode::matches($store, $first) && SetupCode::matches($store, $second), 'only the newest works');
    same(true, SetupCode::attempt($store, $second, '203.0.113.1'), 'the CLI cleared the limits');
    $third = $grab($run([]));
    check($third !== '' && $third !== $second && SetupCode::matches($store, $third), 'every run rotates');
    $store->write('config', ['installed_at' => 1700000000]);
    check(str_contains($run([]), 'already set up'), 'installed message');
    same('', $run(['--if-needed']), 'silent once installed');
});
finish();
