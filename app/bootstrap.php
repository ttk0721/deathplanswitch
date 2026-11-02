<?php
/**
 * Death Plan Switch: bootstrap.
 *
 * Loaded by every entry point (web and CLI). It defines paths, the class
 * autoloader and a handful of small helpers. It has no side effects beyond
 * that, so requesting this file directly over the web does nothing.
 */
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    define('DPS_ROOT', dirname(__DIR__));
}

// The app version: the VERSION file under DPS_ROOT when it holds X.Y.Z, else 1.0.0.
define('DPS\VERSION', (static function (): string {
    $file = DPS_ROOT . '/VERSION';
    $version = is_file($file) ? trim((string) file_get_contents($file)) : '';
    return preg_match('/^\d+\.\d+\.\d+$/D', $version) === 1 ? $version : '1.0.0';
})());

// Optional: config.local.php may define DPS_DATA to move the data folder
// outside the web root. See README, "Moving the data folder".
if (is_file(DPS_ROOT . '/config.local.php')) {
    require DPS_ROOT . '/config.local.php';
}
if (!defined('DPS_DATA') && is_string(getenv('DPS_DATA')) && getenv('DPS_DATA') !== '') {
    define('DPS_DATA', rtrim(getenv('DPS_DATA'), '/'));
}
if (!defined('DPS_DATA')) {
    define('DPS_DATA', DPS_ROOT . '/data');
}

error_reporting(E_ALL);
ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC'); // everything is stored in UTC; display uses the owner's timezone

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'DPS\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    } elseif (str_starts_with($class, 'PHPMailer\\PHPMailer\\')) {
        $file = __DIR__ . '/lib/PHPMailer/' . substr($class, 20) . '.php';
    } else {
        return;
    }
    if (is_file($file)) {
        require $file;
    }
});

/** Escape for HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** The one shared store for this install. */
function store(): Store
{
    static $store = null;
    return $store ??= new Store(DPS_DATA);
}

/** The engine, wired to real SMTP using the saved settings. */
function engine(): Engine
{
    static $engine = null;
    if ($engine === null) {
        $store = store();
        $engine = new Engine($store, new Outbox($store, new SmtpTransport(), new HttpNotifier(appriseUrl())));
    }
    return $engine;
}

/** Address of the apprise-api sidecar: saved setting, else DPS_APPRISE_URL, else ''. */
function appriseUrl(): string
{
    try {
        if (store()->exists('config')) {
            $saved = trim((string) (store()->read('config')['apprise_url'] ?? ''));
            if ($saved !== '') {
                return $saved;
            }
        }
    } catch (\Throwable) {
    }
    $env = getenv('DPS_APPRISE_URL');
    return is_string($env) ? trim($env) : '';
}

/** Translate an English source string; {name} placeholders are filled from $vars. */
function t(string $en, array $vars = []): string
{
    return I18n::t($en, $vars);
}

/** A counted phrase: tn('{n} hour', '{n} hours', $n). The first form is the key in the lang files. */
function tn(string $one, string $other, int $n, array $vars = []): string
{
    return I18n::tn($one, $other, $n, $vars);
}

/** The interface language: any registered code with a lang/<code>/ folder, else 'en'. */
function lang(): string
{
    return I18n::lang();
}

/**
 * Format a UTC timestamp in the owner's timezone.
 * $format is a name from the language's dates.php ('full', 'date', 'time', ...) or a date() pattern.
 */
function when(?int $ts, string $tz = 'UTC', string $format = 'full'): string
{
    if (!$ts) {
        return t('never');
    }
    try {
        $zone = new \DateTimeZone($tz);
    } catch (\Throwable) {
        $zone = new \DateTimeZone('UTC');
    }
    $date = (new \DateTimeImmutable('@' . $ts))->setTimezone($zone);
    $dates = I18n::dates();
    return formatDate($date, $dates['formats'][$format] ?? $format, $dates);
}

/**
 * Format a date with a language's dates data: names for l D F M, its am/pm text for a A, and
 * the 24-hour letters G H in place of g h when the language has no 12-hour clock.
 * A backslash makes the next character literal.
 */
function formatDate(\DateTimeImmutable $date, string $format, array $dates): string
{
    $weekday = (int) $date->format('w');
    $month = (int) $date->format('n') - 1;
    $hour12 = !empty($dates['hour12']);
    $half = (int) $date->format('G') < 12 ? 'am' : 'pm';
    $out = '';
    $len = strlen($format);
    for ($i = 0; $i < $len; $i++) {
        $c = $format[$i];
        $out .= match ($c) {
            '\\' => $format[++$i] ?? '',
            'l' => $dates['weekdays'][$weekday],
            'D' => $dates['weekdays_short'][$weekday],
            'F' => ($dates['months_genitive'] ?? $dates['months'])[$month],
            'M' => $dates['months_short'][$month],
            'g' => $hour12 ? $date->format('g') : $date->format('G'),
            'h' => $hour12 ? $date->format('h') : $date->format('H'),
            'a' => $hour12 ? (string) $dates[$half] : '',
            'A' => $hour12 ? mb_strtoupper((string) $dates[$half]) : '',
            default => $date->format($c),
        };
    }
    return $out;
}

/** "3 hours", "2 days": a rough human duration. */
function duration(int $seconds): string
{
    $seconds = abs($seconds);
    if ($seconds < 90) {
        return t('a minute');
    }
    if ($seconds < 3600 * 1.5) {
        return tn('{n} minute', '{n} minutes', (int) round($seconds / 60));
    }
    if ($seconds < 86400 * 2) {
        return tn('{n} hour', '{n} hours', (int) round($seconds / 3600));
    }
    return tn('{n} day', '{n} days', (int) round($seconds / 86400));
}

/** "in 3 hours" or "3 hours ago", worded by the language's dates.php. */
function relative(?int $ts): string
{
    if (!$ts) {
        return t('never');
    }
    $words = I18n::dates()['relative'];
    $diff = $ts - Clock::now();
    if (abs($diff) < 60) {
        return (string) $words['now'];
    }
    return strtr((string) $words[$diff >= 0 ? 'future' : 'past'], ['{duration}' => duration($diff)]);
}

/** True inside the Docker image, where DPS_DOCKER=1 is set. */
function inDocker(): bool
{
    return getenv('DPS_DOCKER') === '1';
}

/** 9 becomes "9:00am", or "9:00" in a 24-hour language. */
function hourLabel(int $hour): string
{
    $at = new \DateTimeImmutable('@' . gmmktime(min(23, max(0, $hour)), 0, 0, 1, 1, 2026));
    $dates = I18n::dates();

    return formatDate($at, (string) $dates['formats']['time'], $dates);
}
