<?php
declare(strict_types=1);
namespace DPS\Tests;
require __DIR__ . '/lib.php';

/** @return list<string> */
function sourceFiles(): array
{
    $root = dirname(__DIR__);
    $files = glob($root . '/*.php') ?: [];
    $files = array_merge($files, glob($root . '/cli/*.php') ?: []);
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app', \FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() === 'php' && !str_contains($f->getPathname(), '/app/lib/')) {
            $files[] = $f->getPathname();
        }
    }
    sort($files);
    return $files;
}

/** @return list<array{file:string,line:int,text:string,plural:bool}> */
function usedKeys(): array
{
    $root = dirname(__DIR__) . '/';
    $found = [];
    foreach (sourceFiles() as $file) {
        $sig = [];
        foreach (token_get_all((string) file_get_contents($file)) as $tok) {
            if (!is_array($tok) || !in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $sig[] = $tok;
            }
        }
        $m = count($sig);
        for ($i = 0; $i + 2 < $m; $i++) {
            $tok = $sig[$i];
            if (!is_array($tok)) {
                continue;
            }
            if ($tok[0] === T_STRING) {
                $isName = $tok[1] === 't' || $tok[1] === 'tn';
            } else {
                $isName = in_array($tok[0], [T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)
                    && preg_match('/^\\\\?DPS\\\\tn?$/', $tok[1]) === 1;
            }
            if (!$isName || $sig[$i + 1] !== '(') {
                continue;
            }
            $prev = $i > 0 ? $sig[$i - 1] : null;
            if (is_array($prev) && in_array($prev[0], [T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR, T_NEW], true)) {
                continue;
            }
            $arg = $sig[$i + 2];
            if (is_array($arg) && $arg[0] === T_CONSTANT_ENCAPSED_STRING) {
                $found[] = ['file' => str_replace($root, '', $file), 'line' => $arg[2], 'text' => eval('return ' . $arg[1] . ';'), 'plural' => str_ends_with($tok[1], 'tn')];
            }
        }
    }
    return $found;
}

/** Registered languages that have files to check (not en, which is the source). @return array<string,array> */
function checkedLanguages(): array
{
    $out = [];
    $only = array_filter(array_map('trim', explode(',', (string) getenv('DPS_I18N_ONLY'))));
    foreach (require dirname(__DIR__) . '/lang/languages.php' as $code => $info) {
        if ($only !== [] && !in_array((string) $code, $only, true)) {
            continue;
        }
        if ($code !== 'en' && is_dir(dirname(__DIR__) . '/lang/' . $code)) {
            $out[(string) $code] = $info;
        }
    }
    return $out;
}

/** @return array<string,array<string,string|list<string>>> file => entries */
function langFiles(string $code): array
{
    $out = [];
    foreach (glob(dirname(__DIR__) . '/lang/' . $code . '/*.php') ?: [] as $f) {
        if (basename($f) !== 'dates.php') {
            $out[basename($f)] = require $f;
        }
    }
    return $out;
}

/** @return list<string> */
function placeholders(string $s): array
{
    preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $s, $m);
    $p = array_values(array_unique($m[1]));
    sort($p);
    return $p;
}

/** @param list<string> $problems */
function noProblems(array $problems, string $what): void
{
    check($problems === [], count($problems) . ' ' . $what . ":\n    " . implode("\n    ", $problems));
}

test('the registry is well formed', function (): void {
    $all = require dirname(__DIR__) . '/lang/languages.php';
    check(isset($all['en']), 'en is registered');
    $bad = [];
    foreach ($all as $code => $info) {
        if (trim((string) ($info['name'] ?? '')) === '') {
            $bad[] = $code . ': no native name';
        }
        if (!in_array($info['dir'] ?? '', ['ltr', 'rtl'], true)) {
            $bad[] = $code . ': dir must be ltr or rtl';
        }
        if (!isset(\DPS\Plural::RULES[$info['plural'] ?? ''])) {
            $bad[] = $code . ': unknown plural rule';
        }
    }
    noProblems($bad, 'registry problems');
});

foreach (array_keys(checkedLanguages()) as $code) {
    $forms = \DPS\Plural::count((string) checkedLanguages()[$code]['plural']);

    test("[$code] every t() literal and plural key has an entry", function () use ($code, $forms): void {
        $merged = [];
        foreach (langFiles($code) as $entries) {
            $merged += $entries;
        }
        $used = usedKeys();
        check(count($used) > 0, 'found t() calls');
        $missing = [];
        foreach ($used as $u) {
            $where = $u['file'] . ':' . $u['line'] . '  ' . $u['text'];
            if (!array_key_exists($u['text'], $merged)) {
                $missing[] = $where;
            } elseif ($u['plural'] && !(is_array($merged[$u['text']]) && count($merged[$u['text']]) === $forms)) {
                $missing[] = $where . "  (needs a list of $forms forms)";
            } elseif (!$u['plural'] && !is_string($merged[$u['text']])) {
                $missing[] = $where . '  (must be text, not a list of forms)';
            }
        }
        noProblems($missing, 'missing or malformed entries');
    });

    test("[$code] no key has two different values", function () use ($code): void {
        $seen = [];
        $conflicts = [];
        foreach (langFiles($code) as $name => $entries) {
            foreach ($entries as $en => $value) {
                if (isset($seen[$en]) && $seen[$en][1] !== $value) {
                    $conflicts[] = $en . '  [' . $seen[$en][0] . ' | ' . $name . ']';
                }
                $seen[$en] ??= [$name, $value];
            }
        }
        noProblems($conflicts, 'conflicting keys');
    });

    test("[$code] placeholders match the English and no value is empty", function () use ($code, $forms): void {
        $bad = [];
        foreach (langFiles($code) as $name => $entries) {
            foreach ($entries as $en => $value) {
                $list = is_array($value) ? $value : [$value];
                if (is_array($value) && count($value) !== $forms) {
                    $bad[] = $name . ': ' . $en . '  has ' . count($value) . " forms, needs $forms";
                }
                foreach ($list as $text) {
                    if (!is_string($text) || trim($text) === '') {
                        $bad[] = $name . ': ' . $en . '  has an empty value';
                    } elseif (placeholders((string) $en) !== placeholders($text)) {
                        $bad[] = $name . ': ' . $en . '  =>  ' . $text;
                    }
                }
            }
        }
        noProblems($bad, 'problems');
    });

    test("[$code] the bracketed gap marker is kept in templates", function () use ($code): void {
        $bad = [];
        foreach (langFiles($code) as $name => $entries) {
            foreach ($entries as $en => $value) {
                if (is_string($value) && preg_match_all('/\[write here: /', (string) $en) !== preg_match_all('/\[write here: /', $value)) {
                    $bad[] = $name . ': ' . $en;
                }
            }
        }
        noProblems($bad, 'templates that lost their gap marker');
    });
}

/** Names and shapes every dates.php must have. @return list<string> */
function datesProblems(array $dates, string $label): array
{
    $bad = [];
    foreach (['weekdays' => 7, 'weekdays_short' => 7, 'months' => 12, 'months_short' => 12] as $key => $count) {
        if (!isset($dates[$key]) || !is_array($dates[$key]) || count($dates[$key]) !== $count) {
            $bad[] = "$label: '$key' needs $count entries";
        }
    }
    if (isset($dates['months_genitive']) && count((array) $dates['months_genitive']) !== 12) {
        $bad[] = "$label: 'months_genitive' needs 12 entries";
    }
    if (!isset($dates['hour12']) || !is_bool($dates['hour12'])) {
        $bad[] = "$label: 'hour12' must be true or false";
    }
    foreach (['full', 'date', 'day', 'time', 'long', 'stamp', 'brief', 'letter_date', 'letter_day'] as $key) {
        if (trim((string) ($dates['formats'][$key] ?? '')) === '') {
            $bad[] = "$label: format '$key' is missing";
        }
    }
    foreach (['future', 'past', 'now'] as $key) {
        $text = (string) ($dates['relative'][$key] ?? '');
        if (trim($text) === '') {
            $bad[] = "$label: relative '$key' is missing";
        } elseif (($key === 'now') === (placeholders($text) !== [])) {
            $bad[] = "$label: relative '$key' " . ($key === 'now' ? 'takes no placeholder' : 'needs {duration}');
        }
    }
    foreach (['weekdays', 'weekdays_short', 'months', 'months_short', 'months_genitive'] as $key) {
        foreach ((array) ($dates[$key] ?? []) as $name) {
            if (!is_string($name) || trim($name) === '') {
                $bad[] = "$label: empty name in '$key'";
            }
        }
    }
    return $bad;
}

test('every registered language has complete dates', function (): void {
    $bad = datesProblems(require dirname(__DIR__) . '/lang/en/dates.php', 'en');
    foreach (array_keys(checkedLanguages()) as $code) {
        $file = dirname(__DIR__) . '/lang/' . $code . '/dates.php';
        check(is_file($file), "$code has dates.php");
        $bad = array_merge($bad, datesProblems(require $file, $code));
    }
    noProblems($bad, 'dates problems');
});

test('the catalog lists the plural keys with a form count per rule', function (): void {
    $out = (string) shell_exec(PHP_BINARY . ' ' . escapeshellarg(dirname(__DIR__) . '/bin/i18n-catalog.php'));
    $catalog = json_decode($out, true);
    check(is_array($catalog), 'catalog is JSON');
    foreach (['core', 'switch', 'contacts', 'settings', 'install', 'pages', 'messages', 'engine', 'history', 'dates', 'js'] as $area) {
        check(isset($catalog['areas'][$area]), "area $area");
    }
    same(3, $catalog['areas']['core']['plurals']['{n} day']['pl'], 'pl forms');
    same(6, $catalog['areas']['core']['plurals']['{n} day']['ar'], 'ar forms');
    check(in_array('never', $catalog['areas']['core']['strings'], true), 'core strings');
});
finish();
