<?php
/** Translation lookup: English source strings to lang/<language>/*.php. */
declare(strict_types=1);

namespace DPS;

final class I18n
{
    private static ?string $lang = null;
    /** @var array<string,array<string,string>> */
    private static array $cache = [];

    /** Tests only: force a language; null re-reads the config. Unknown values mean en. */
    public static function setLanguage(?string $lang): void
    {
        self::$lang = $lang === null ? null : self::valid($lang);
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

    public static function t(string $en, array $vars = []): string
    {
        $lang = self::lang();
        $text = $en;
        if ($lang !== 'en') {
            self::$cache[$lang] ??= self::load($lang);
            $text = self::$cache[$lang][$en] ?? $en;
        }
        if ($vars) {
            $map = [];
            foreach ($vars as $k => $v) {
                $map['{' . $k . '}'] = (string) $v;
            }
            $text = strtr($text, $map);
        }
        return $text;
    }

    private static function valid(string $lang): string
    {
        return in_array($lang, ['pl', 'en'], true) ? $lang : 'en';
    }

    /** @return array<string,string> */
    private static function load(string $lang): array
    {
        $all = [];
        foreach (glob(DPS_ROOT . '/lang/' . $lang . '/*.php') ?: [] as $file) {
            $part = require $file;
            if (is_array($part)) {
                $all = array_merge($all, $part);
            }
        }
        return $all;
    }
}
