<?php
declare(strict_types=1);

namespace DPS;

/**
 * The one-time setup code. Before the first install nobody has a password, so
 * the installer asks for a code that only someone who can read the server's
 * logs or files can know. It sits in <data>/setup-code and is deleted when
 * the install finishes.
 *
 * Format: 20 Crockford base32 characters in groups of four, for example
 * 7QK2-M9XT-0HVB-D4RN-Z8PE.
 */
final class SetupCode
{
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

    /** The current code, or null when there is none. */
    public static function read(Store $store): ?string
    {
        $raw = @file_get_contents(self::path($store));
        $code = is_string($raw) ? trim($raw) : '';
        return preg_match('/^[0-9A-Z]{4}(-[0-9A-Z]{4}){4}$/D', $code) === 1 ? $code : null;
    }

    /**
     * The code for an install that is not finished: made on first use, then kept.
     * Returns null once the install is finished.
     */
    public static function ensure(Store $store): ?string
    {
        if (self::installed($store)) {
            return null;
        }
        $existing = self::read($store);
        if ($existing !== null) {
            return $existing;
        }
        if (!is_dir($store->dir()) && !@mkdir($store->dir(), 0700, true) && !is_dir($store->dir())) {
            return null;
        }
        // "x" fails if another process got there first; then we read theirs.
        $handle = @fopen(self::path($store), 'x');
        if ($handle === false) {
            return self::read($store);
        }
        @chmod(self::path($store), 0600);
        $code = self::generate();
        fwrite($handle, $code . "\n");
        fclose($handle);
        return $code;
    }

    public static function delete(Store $store): void
    {
        @unlink(self::path($store));
    }

    /** The two lines that tell the owner where to type it. */
    public static function announcement(string $code): string
    {
        return "Setup code: {$code}\nOpen /install.php and enter it.\n";
    }

    /** Print the announcement when a code is due; used by the container, the scheduler and the CLI. */
    public static function announce(Store $store): void
    {
        try {
            $code = self::ensure($store);
        } catch (\Throwable) {
            return;
        }
        if ($code !== null) {
            echo self::announcement($code);
        }
    }

    /**
     * Check a typed code, with the same growing delay as the sign-in.
     *
     * @return true|string true, or the reason it is refused
     */
    public static function attempt(Store $store, string $typed): bool|string
    {
        return $store->locked(static function () use ($store, $typed): bool|string {
            $now = Clock::now();
            $auth = $store->read(self::DOCUMENT) + ['failures' => 0, 'locked_until' => 0];
            if ($auth['locked_until'] > $now) {
                return t('Too many wrong codes. Try again {when}.', ['when' => relative((int) $auth['locked_until'])]);
            }
            $code = self::read($store);
            if ($code !== null && hash_equals(self::normalise($code), self::normalise($typed))) {
                if ($auth['failures'] > 0) {
                    $store->write(self::DOCUMENT, ['failures' => 0, 'locked_until' => 0]);
                }
                return true;
            }
            $auth['failures']++;
            if ($auth['failures'] >= 5) {
                $auth['locked_until'] = $now + min(3600, 60 * 2 ** ($auth['failures'] - 5));
            }
            $store->write(self::DOCUMENT, $auth);
            return t('That setup code is not right.');
        });
    }
}
