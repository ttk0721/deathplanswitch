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

/** @return list<array{file:string,line:int,text:string}> */
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
                $isName = $tok[1] === 't';
            } else {
                $isName = in_array($tok[0], [T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)
                    && preg_match('/^\\\\?DPS\\\\t$/', $tok[1]) === 1;
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
                $found[] = ['file' => str_replace($root, '', $file), 'line' => $arg[2], 'text' => eval('return ' . $arg[1] . ';')];
            }
        }
    }
    return $found;
}

/** @return array<string,array<string,string>> file => entries */
function polishFiles(): array
{
    $out = [];
    foreach (glob(dirname(__DIR__) . '/lang/pl/*.php') ?: [] as $f) {
        $out[basename($f)] = require $f;
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

test('every t() literal has a Polish entry', function (): void {
    $merged = [];
    foreach (polishFiles() as $entries) {
        $merged += $entries;
    }
    $used = usedKeys();
    check(count($used) > 0, 'found t() calls');
    $missing = [];
    foreach ($used as $u) {
        if (!array_key_exists($u['text'], $merged)) {
            $missing[] = $u['file'] . ':' . $u['line'] . '  ' . $u['text'];
        }
    }
    check($missing === [], count($missing) . " missing Polish entries:\n    " . implode("\n    ", $missing));
});

test('no key has two different Polish values', function (): void {
    $seen = [];
    $conflicts = [];
    foreach (polishFiles() as $name => $entries) {
        foreach ($entries as $en => $pl) {
            if (isset($seen[$en]) && $seen[$en][1] !== $pl) {
                $conflicts[] = $en . '  [' . $seen[$en][0] . ': ' . $seen[$en][1] . ' | ' . $name . ': ' . $pl . ']';
            }
            $seen[$en] ??= [$name, $pl];
        }
    }
    check($conflicts === [], count($conflicts) . " conflicting keys:\n    " . implode("\n    ", $conflicts));
});

test('placeholders match between English and Polish', function (): void {
    $bad = [];
    foreach (polishFiles() as $name => $entries) {
        foreach ($entries as $en => $pl) {
            if (placeholders((string) $en) !== placeholders((string) $pl)) {
                $bad[] = $name . ': ' . $en . '  =>  ' . $pl;
            }
        }
    }
    check($bad === [], count($bad) . " placeholder mismatches:\n    " . implode("\n    ", $bad));
});
finish();
