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

/** The interface language: 'pl' or 'en'. */
function lang(): string
{
    return I18n::lang();
}

/** Format a UTC timestamp in the owner's timezone. */
function when(?int $ts, string $tz = 'UTC', string $format = 'l j F Y, g:ia'): string
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
    return lang() === 'pl' ? whenPl($date, $format) : $date->format($format);
}

/** Polish date text: nominative weekdays, genitive months, 24-hour clock, no am/pm. */
function whenPl(\DateTimeImmutable $date, string $format): string
{
    $days = ['niedziela', 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota'];
    $daysShort = ['niedz.', 'pon.', 'wt.', 'śr.', 'czw.', 'pt.', 'sob.'];
    $months = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];
    $monthsShort = ['sty', 'lut', 'mar', 'kwi', 'maj', 'cze', 'lip', 'sie', 'wrz', 'paź', 'lis', 'gru'];
    $out = '';
    $len = strlen($format);
    for ($i = 0; $i < $len; $i++) {
        $c = $format[$i];
        $out .= match ($c) {
            '\\' => $format[++$i] ?? '',
            'l' => $days[(int) $date->format('w')],
            'D' => $daysShort[(int) $date->format('w')],
            'F' => $months[(int) $date->format('n') - 1],
            'M' => $monthsShort[(int) $date->format('n') - 1],
            'g' => $date->format('G'),
            'h' => $date->format('H'),
            'a', 'A' => '',
            default => $date->format($c),
        };
    }
    return $out;
}

/** "3 hours", "2 days": a rough human duration. */
function duration(int $seconds): string
{
    $seconds = abs($seconds);
    $pl = lang() === 'pl';
    if ($seconds < 90) {
        return t('a minute');
    }
    if ($seconds < 3600 * 1.5) {
        $n = (int) round($seconds / 60);
        return $pl ? $n . ' ' . plPlural($n, 'minuta', 'minuty', 'minut') : $n . ' minutes';
    }
    if ($seconds < 86400 * 2) {
        $n = (int) round($seconds / 3600);
        return $pl ? $n . ' ' . plPlural($n, 'godzina', 'godziny', 'godzin') : $n . ' hours';
    }
    $n = (int) round($seconds / 86400);
    return $pl ? $n . ' ' . plPlural($n, 'dzień', 'dni', 'dni') : $n . ' days';
}

/** Polish plural form: 1, 2-4 (not 12-14), 5+. */
function plPlural(int $n, string $one, string $few, string $many): string
{
    if ($n === 1) {
        return $one;
    }
    $mod10 = $n % 10;
    $mod100 = $n % 100;
    return ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) ? $few : $many;
}

/** "in 3 hours" or "3 hours ago". */
function relative(?int $ts): string
{
    if (!$ts) {
        return t('never');
    }
    $diff = $ts - Clock::now();
    if (abs($diff) < 60) {
        return t('just now');
    }
    return t($diff >= 0 ? 'in {duration}' : '{duration} ago', ['duration' => duration($diff)]);
}

/** True inside the Docker image, where DPS_DOCKER=1 is set. */
function inDocker(): bool
{
    return getenv('DPS_DOCKER') === '1';
}

/** 9 becomes "9:00am". */
function hourLabel(int $hour): string
{
    $at = gmmktime(min(23, max(0, $hour)), 0, 0, 1, 1, 2026);

    return gmdate(lang() === 'pl' ? 'G:i' : 'g:ia', $at);
}
