<?php
/**
 * Translator catalog: every English source string a language must cover, as JSON.
 * Usage: php bin/i18n-catalog.php
 *
 * Per area (the lang/<code>/<area>.php files, "dates" and the JS file) it lists the
 * English keys, and the plural keys with how many forms each plural rule needs.
 * The reference set is lang/pl/ and assets/lang/pl.js, which the test suite keeps complete.
 */
declare(strict_types=1);

namespace DPS;

define('DPS_ROOT', dirname(__DIR__));
require DPS_ROOT . '/app/Plural.php';

$formsPerRule = [];
foreach (Plural::RULES as $rule => $categories) {
    $formsPerRule[$rule] = count($categories);
}

$areas = [];
foreach (glob(DPS_ROOT . '/lang/pl/*.php') ?: [] as $file) {
    $area = basename($file, '.php');
    if ($area === 'dates') {
        continue;
    }
    $entries = require DPS_ROOT . '/lang/pl/' . $area . '.php';
    $areas[$area] = splitEntries($area, $entries, $formsPerRule);
}

$dates = require DPS_ROOT . '/lang/en/dates.php';
$shape = [];
foreach ($dates as $key => $value) {
    $shape[$key] = is_array($value)
        ? (array_is_list($value) ? ['list' => count($value)] : ['keys' => array_keys($value)])
        : (is_bool($value) ? 'true or false' : 'text');
}
$areas['dates'] = ['file' => 'lang/<code>/dates.php', 'shape' => $shape];

$js = [];
$source = (string) file_get_contents(DPS_ROOT . '/assets/lang/pl.js');
preg_match_all("/^\\s*'((?:\\\\.|[^'\\\\])*)'\\s*:\\s*(\\[|')/m", $source, $matches, PREG_SET_ORDER);
foreach ($matches as $m) {
    $js[stripcslashes($m[1])] = $m[2] === '[' ? [] : 'text';
}
$jsStrings = [];
$jsPlurals = [];
foreach ($js as $key => $kind) {
    if ($kind === 'text') {
        $jsStrings[] = $key;
    } else {
        $jsPlurals[$key] = $formsPerRule;
    }
}
$areas['js'] = ['file' => 'assets/lang/<code>.js', 'strings' => $jsStrings, 'plurals' => $jsPlurals];

echo json_encode(
    ['plural_rules' => Plural::RULES, 'areas' => $areas],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
), "\n";

/**
 * @param array<string,string|list<string>> $entries
 * @param array<string,int> $formsPerRule
 * @return array{file:string,strings:list<string>,plurals:array<string,array<string,int>>}
 */
function splitEntries(string $area, array $entries, array $formsPerRule): array
{
    $strings = [];
    $plurals = [];
    foreach ($entries as $key => $value) {
        if (is_array($value)) {
            $plurals[(string) $key] = $formsPerRule;
        } else {
            $strings[] = (string) $key;
        }
    }
    return ['file' => 'lang/<code>/' . $area . '.php', 'strings' => $strings, 'plurals' => $plurals];
}
