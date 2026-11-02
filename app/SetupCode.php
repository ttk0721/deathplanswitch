<?php
declare(strict_types=1);

namespace DPS;

/**
 * The one-time setup code. Before the first install nobody has a password, so
 * the installer asks for a code that only someone who can read the server's
 * logs can know. Only a salted SHA-256 hash of the code is kept in
 * <data>/setup-code, so a data folder served by mistake does not give it
 * away. The code itself exists in memory when it is made and is printed once
 * (container or service log, or the CLI). Every start of the web container or
 * the scheduler, and every run of cli/setup-code.php, makes a fresh code: the
 * newest one printed is the valid one. The file is deleted when the install
 * finishes.
 *
 * Wrong codes are limited per client (REMOTE_ADDR): 10 attempts per 15
 * minutes. There is no global lock, so one client cannot lock out another.
 *
 * Format: 20 Crockford base32 characters in groups of four, for example
 * 7QK2-M9XT-0HVB-D4RN-Z8PE.
 */
final class SetupCode
{
    public const MAX_ATTEMPTS = 10;
    public const WINDOW = 900;
    private const MAX_CLIENTS = 2000;
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const DOCUMENT = 'setup_auth';

    public static function path(Store $store): string
    {
        return $store->dir() . '/setup-code';
    }

    /** True when the install is finished and no code is needed. */
    public static function installed(Store $store): bool
    {
        try {
            return !empty($store->read('config')['installed_at']);
        } catch (StoreException) {
            return true; // a damaged data folder is not the time to hand out a code
        }
    }

    /** A new random code, in groups. */
    public static function generate(): string
    {
        $bytes = random_bytes(20);
        $out = '';
        for ($i = 0; $i < 20; $i++) {
            $out .= self::ALPHABET[ord($bytes[$i]) & 31];
        }
        return implode('-', str_split($out, 4));
    }

    /** What a person typed, reduced to the 20 characters (Crockford: I and L are 1, O is 0). */
    public static function normalise(string $typed): string
    {
        $s = strtr(strtoupper($typed), ['I' => '1', 'L' => '1', 'O' => '0']);
        return (string) preg_replace('/[^0-9A-Z]/', '', $s);
    }

    /** The stored salt and hash, or null when there is no valid file. */
    private static function stored(Store $store): ?array
    {
        $raw = @file_get_contents(self::path($store));
        if (!is_string($raw) || preg_match('/^v1:([0-9a-f]{32}):([0-9a-f]{64})$/D', trim($raw), $m) !== 1) {
            return null;
        }
        return ['salt' => $m[1], 'hash' => $m[2]];
    }

    private static function digest(string $salt, string $typed): string
    {
        return hash('sha256', $salt . ':' . self::normalise($typed));
    }

    /** True when a hash is stored (not whether any given code matches). */
    public static function exists(Store $store): bool
    {
        return self::stored($store) !== null;
    }

    /** True when $typed matches the stored hash. Constant-time comparison. */
    public static function matches(Store $store, string $typed): bool
    {
        $stored = self::stored($store);
        if ($stored === null) {
            return false;
        }
        return hash_equals($stored['hash'], self::digest($stored['salt'], $typed));
    }

    /**
     * Make a fresh code for an install that is not finished, store only its
     * hash (replacing any earlier one) and clear the rate limits. Returns the
     * plain code, which exists nowhere else, or null once the install is
     * finished or the hash cannot be written.
     */
    public static function rotate(Store $store): ?string
    {
        if (self::installed($store)) {
            return null;
        }
        if (!is_dir($store->dir()) && !@mkdir($store->dir(), 0700, true) && !is_dir($store->dir())) {
            return null;
        }
        $code = self::generate();
        $salt = bin2hex(random_bytes(16));
        $tmp = self::path($store) . '.tmp.' . bin2hex(random_bytes(6));
        if (@file_put_contents($tmp, 'v1:' . $salt . ':' . self::digest($salt, $code) . "\n") === false) {
            @unlink($tmp);
            return null;
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, self::path($store))) {
            @unlink($tmp);
            return null;
        }
        self::clearLimits($store);
        return $code;
    }

    /** Forget every client's failed attempts. */
    public static function clearLimits(Store $store): void
    {
        @unlink($store->path(self::DOCUMENT));
        @unlink($store->path(self::DOCUMENT) . '.bak');
    }

    public static function delete(Store $store): void
    {
        @unlink(self::path($store));
        self::clearLimits($store);
    }

    /** The two lines that tell the owner where to type it. */
    public static function announcement(string $code): string
    {
        return "Setup code: {$code}\nOpen /install.php and enter it.\n";
    }

    /** Make a fresh code and print it when the install is not finished; used by the container, the scheduler and the CLI. */
    public static function announce(Store $store): void
    {
        try {
            $code = self::rotate($store);
        } catch (\Throwable) {
            return;
        }
        if ($code !== null) {
            echo self::announcement($code);
        }
    }

    /** The limiter key for the current request: the address Apache resolved as the client. */
    private static function client(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /**
     * Check a typed code. Each client (REMOTE_ADDR) gets 10 wrong attempts per
     * 15 minutes; a client that is not over its own limit is never refused
     * because of anyone else's failures.
     *
     * @return true|string true, or the reason it is refused
     */
    public static function attempt(Store $store, string $typed, ?string $client = null): bool|string
    {
        $key = substr(hash('sha256', $client ?? self::client()), 0, 24);
        return $store->locked(static function () use ($store, $typed, $key): bool|string {
            $now = Clock::now();
            $auth = $store->read(self::DOCUMENT);
            $clients = [];
            foreach ((array) ($auth['clients'] ?? []) as $k => $c) {
                if (is_array($c) && (int) ($c['since'] ?? 0) + self::WINDOW > $now) {
                    $clients[(string) $k] = ['n' => (int) ($c['n'] ?? 0), 'since' => (int) $c['since']];
                }
            }
            $mine = $clients[$key] ?? null;
            if ($mine !== null && $mine['n'] >= self::MAX_ATTEMPTS) {
                return t('Too many wrong codes. Try again {when}.', ['when' => relative($mine['since'] + self::WINDOW)]);
            }
            if (self::matches($store, $typed)) {
                if ($mine !== null) {
                    unset($clients[$key]);
                    $store->write(self::DOCUMENT, ['clients' => $clients]);
                }
                return true;
            }
            $clients[$key] = ['n' => ($mine['n'] ?? 0) + 1, 'since' => $mine['since'] ?? $now];
            if (count($clients) > self::MAX_CLIENTS) {
                uasort($clients, static fn (array $a, array $b): int => $a['since'] <=> $b['since']);
                $clients = array_slice($clients, -self::MAX_CLIENTS, null, true);
            }
            $store->write(self::DOCUMENT, ['clients' => $clients]);
            return t('That setup code is not right.');
        });
    }
}
