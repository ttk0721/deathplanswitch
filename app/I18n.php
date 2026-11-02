<?php
/** Translation lookup: English source strings to lang/<language>/*.php. */
declare(strict_types=1);

namespace DPS;

final class I18n
{
    private static ?string $lang = null;
    /** @var array<string,array<string,string|list<string>>> */
    private static array $cache = [];
    /** @var array<string,array<string,mixed>> */
    private static array $dates = [];
    /** @var ?array<string,array{name:string,dir:string,plural:string}> */
    private static ?array $registry = null;

    /** Tests only: force a language; null re-reads the config. Unknown values mean en. */
    public static function setLanguage(?string $lang): void
    {
        self::$lang = $lang === null ? null : self::valid($lang);
    }

    /** Tests only: replace the registry; null reloads lang/languages.php. */
    public static function useRegistry(?array $registry): void
    {
        self::$registry = $registry;
        self::$cache = [];
        self::$dates = [];
    }

    /** Every registered language: code => name, dir, plural. */
    public static function languages(): array
    {
        if (self::$registry === null) {
            $all = require DPS_ROOT . '/lang/languages.php';
            self::$registry = is_array($all) ? $all : [];
        }
        return self::$registry;
    }

    /** The registered languages that can be used now: en, and any with a lang/<code>/ folder. */
    public static function available(): array
    {
        $out = [];
        foreach (self::languages() as $code => $info) {
            if (self::usable((string) $code)) {
                $out[(string) $code] = $info;
            }
        }
        return $out;
    }

    /** Pick a language from an Accept-Language header; null when none matches. */
    public static function fromAcceptLanguage(string $header): ?string
    {
        $available = array_keys(self::available());
        $wanted = [];
        foreach (explode(',', $header) as $i => $part) {
            $bits = explode(';', trim($part));
            $tag = strtolower(trim($bits[0]));
            $q = 1.0;
            if (isset($bits[1]) && preg_match('/q=([0-9.]+)/', $bits[1], $m) === 1) {
                $q = (float) $m[1];
            }
            if ($tag !== '' && $tag !== '*' && $q > 0) {
                $wanted[] = [$tag, $q, $i];
            }
        }
        usort($wanted, static fn (array $a, array $b): int => [$b[1], $a[2]] <=> [$a[1], $b[2]]);
        foreach ($wanted as [$tag]) {
            $base = explode('-', $tag)[0];
            foreach ($available as $code) {
                if (strtolower($code) === $tag) {
                    return $code;
                }
            }
            foreach ($available as $code) {
                if (explode('-', strtolower($code))[0] === $base) {
                    return $code;
                }
            }
        }
        return null;
    }

    public static function lang(): string
    {
        if (self::$lang !== null) {
            return self::$lang;
        }
        $lang = 'en';
        try {
            if (store()->exists('config')) {
                $lang = self::valid((string) (store()->read('config')['language'] ?? 'en'));
            }
        } catch (\Throwable) {
        }
        return self::$lang = $lang;
    }

    /** 'ltr' or 'rtl' for the current language. */
    public static function dir(): string
    {
        return (self::languages()[self::lang()]['dir'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
    }

    /** The plural rule id of the current language. */
    public static function pluralRule(): string
    {
        $rule = (string) (self::languages()[self::lang()]['plural'] ?? 'one_other');
        return isset(Plural::RULES[$rule]) ? $rule : 'one_other';
    }

    public static function t(string $en, array $vars = []): string
    {
        $text = $en;
        if (self::lang() !== 'en') {
            $found = self::dictionary()[$en] ?? null;
            $text = is_string($found) ? $found : $en;
        }
        return self::fill($text, $vars);
    }

    /**
     * A counted phrase. $one and $other are the English forms ('{n} hour', '{n} hours').
     * $one is the key in the lang file, whose value is a list of forms for the language's rule.
     * {n} is filled with the count.
     */
    public static function tn(string $one, string $other, int $n, array $vars = []): string
    {
        $vars['n'] = $n;
        $text = $n === 1 ? $one : $other;
        if (self::lang() !== 'en') {
            $found = self::dictionary()[$one] ?? null;
            $rule = self::pluralRule();
            if (is_array($found) && count($found) === Plural::count($rule)) {
                $text = (string) $found[Plural::index($rule, $n)];
            }
        }
        return self::fill($text, $vars);
    }

    /**
     * Dates data for the current language (names, formats, relative phrasing),
     * filled from English wherever a language leaves something out.
     * @return array<string,mixed>
     */
    public static function dates(): array
    {
        $lang = self::lang();
        if (isset(self::$dates[$lang])) {
            return self::$dates[$lang];
        }
        $en = self::datesFile('en');
        $own = $lang === 'en' ? [] : self::datesFile($lang);
        $merged = array_replace($en, $own);
        foreach (['formats', 'relative'] as $part) {
            $merged[$part] = array_replace((array) ($en[$part] ?? []), (array) ($own[$part] ?? []));
        }
        return self::$dates[$lang] = $merged;
    }

    private static function fill(string $text, array $vars): string
    {
        if ($vars) {
            $map = [];
            foreach ($vars as $k => $v) {
                $map['{' . $k . '}'] = (string) $v;
            }
            $text = strtr($text, $map);
        }
        return $text;
    }

    private static function usable(string $code): bool
    {
        return preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/D', $code) === 1
            && ($code === 'en' || is_dir(DPS_ROOT . '/lang/' . $code));
    }

    private static function valid(string $lang): string
    {
        return isset(self::languages()[$lang]) && self::usable($lang) ? $lang : 'en';
    }

    /** @return array<string,string|list<string>> */
    private static function dictionary(): array
    {
        $lang = self::lang();
        return self::$cache[$lang] ??= self::load($lang);
    }

    /** @return array<string,mixed> */
    private static function datesFile(string $lang): array
    {
        $file = DPS_ROOT . '/lang/' . $lang . '/dates.php';
        $data = is_file($file) ? require $file : [];
        return is_array($data) ? $data : [];
    }

    /** @return array<string,string|list<string>> */
    private static function load(string $lang): array
    {
        $all = [];
        foreach (glob(DPS_ROOT . '/lang/' . $lang . '/*.php') ?: [] as $file) {
            if (basename($file) === 'dates.php') {
                continue;
            }
            $part = require $file;
            if (is_array($part)) {
                $all = array_merge($all, $part);
            }
        }
        return $all;
    }
}
