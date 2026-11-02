<?php
/** CLDR-style plural rules: which form of a counted phrase to use for a number. */
declare(strict_types=1);

namespace DPS;

final class Plural
{
    /** Rule id => the plural categories it has, in the order a lang file lists the forms. */
    public const RULES = [
        'one_other' => ['one', 'other'],               // en, de, es, it, nl, tr, ...
        'fr' => ['one', 'other'],                      // 0 and 1 are one: fr, pt-BR, hi
        'none' => ['other'],                           // zh, ja, ko, id, vi
        'pl' => ['one', 'few', 'many'],                // pl
        'ru' => ['one', 'few', 'many'],                // ru, uk
        'ar' => ['zero', 'one', 'two', 'few', 'many', 'other'],
    ];

    /** How many forms a rule has. An unknown rule behaves as one_other. */
    public static function count(string $rule): int
    {
        return count(self::RULES[$rule] ?? self::RULES['one_other']);
    }

    /** The category name ('one', 'few', ...) for a number. */
    public static function category(string $rule, int $n): string
    {
        $n = abs($n);
        $mod10 = $n % 10;
        $mod100 = $n % 100;
        switch ($rule) {
            case 'none':
                return 'other';
            case 'fr':
                return $n <= 1 ? 'one' : 'other';
            case 'pl':
                if ($n === 1) {
                    return 'one';
                }
                return ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) ? 'few' : 'many';
            case 'ru':
                if ($mod10 === 1 && $mod100 !== 11) {
                    return 'one';
                }
                return ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) ? 'few' : 'many';
            case 'ar':
                if ($n === 0) {
                    return 'zero';
                }
                if ($n === 1) {
                    return 'one';
                }
                if ($n === 2) {
                    return 'two';
                }
                if ($mod100 >= 3 && $mod100 <= 10) {
                    return 'few';
                }
                return $mod100 >= 11 ? 'many' : 'other';
            default:
                return $n === 1 ? 'one' : 'other';
        }
    }

    /** Position of the form for a number in a lang file's list of forms. */
    public static function index(string $rule, int $n): int
    {
        $i = array_search(self::category($rule, $n), self::RULES[$rule] ?? self::RULES['one_other'], true);
        return $i === false ? 0 : (int) $i;
    }
}
