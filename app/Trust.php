<?php
declare(strict_types=1);

namespace DPS;

/**
 * The settings of the chain of trusted people, kept in config.json under "trust":
 *
 *   mode      'sequential' (one person after another) or 'all_at_once'
 *   interval  null for "auto", or a whole number of minutes (15 to 1440)
 *   window    hours the last person asked has to answer before the switch may fire (6 to 72)
 *
 * Sequential: person k+1 is asked one interval after person k. On "auto" the
 * interval is twelve hours divided by the number of gaps, in whole minutes,
 * so a long list is spread over the same twelve hours as a short one.
 * All at once: the first person is asked, and if no "fine" arrives within the
 * interval (twelve hours on "auto"), everyone else is asked together.
 */
final class Trust
{
    public const MODES = ['sequential', 'all_at_once'];
    public const AUTO_SPREAD = 12 * Schedule::HOUR;
    public const INTERVAL_MIN = 15;
    public const INTERVAL_MAX = 1440;
    public const WINDOW_DEFAULT = 24;
    public const WINDOW_MIN = 6;
    public const WINDOW_MAX = 72;

    /** @return array{mode: string, interval: ?int, window: int} always valid, whatever is stored */
    public static function settings(array $config): array
    {
        $raw = is_array($config['trust'] ?? null) ? $config['trust'] : [];
        $mode = in_array($raw['mode'] ?? null, self::MODES, true) ? (string) $raw['mode'] : 'sequential';
        $interval = $raw['interval'] ?? null;
        $interval = is_int($interval) || (is_string($interval) && ctype_digit($interval))
            ? min(self::INTERVAL_MAX, max(self::INTERVAL_MIN, (int) $interval))
            : null;
        $window = $raw['window'] ?? self::WINDOW_DEFAULT;
        $window = is_int($window) || (is_string($window) && ctype_digit($window))
            ? min(self::WINDOW_MAX, max(self::WINDOW_MIN, (int) $window))
            : self::WINDOW_DEFAULT;
        return ['mode' => $mode, 'interval' => $interval, 'window' => $window];
    }

    /** Seconds between two asks on "auto" for $people people: twelve hours over the gaps, whole minutes. */
    public static function autoStep(int $people): int
    {
        return max(60, (int) round(self::AUTO_SPREAD / max(1, $people - 1) / 60) * 60);
    }

    /**
     * What a cycle needs to know, as seconds.
     *
     * @return array{mode: string, gap: int, window: int}
     */
    public static function timing(array $settings, int $people): array
    {
        if ($settings['mode'] === 'all_at_once') {
            $gap = $settings['interval'] === null ? self::AUTO_SPREAD : $settings['interval'] * 60;
        } else {
            $gap = $settings['interval'] === null ? self::autoStep($people) : $settings['interval'] * 60;
        }
        return ['mode' => $settings['mode'], 'gap' => $gap, 'window' => $settings['window'] * Schedule::HOUR];
    }

    /**
     * Check what the owner typed in Settings.
     *
     * @return array{0: ?array{mode: string, interval: ?int, window: int}, 1: string[]} the settings, or null and the problems
     */
    public static function fromPost(string $mode, string $kind, string $minutes, string $window): array
    {
        $errors = [];
        if (!in_array($mode, self::MODES, true)) {
            $errors[] = t('Choose how the trusted people are asked.');
        }
        $interval = null;
        if ($kind === 'fixed') {
            if (!ctype_digit($minutes) || (int) $minutes < self::INTERVAL_MIN || (int) $minutes > self::INTERVAL_MAX) {
                $errors[] = t('The time between two people must be between {min} minutes and 24 hours.', ['min' => self::INTERVAL_MIN]);
            } else {
                $interval = (int) $minutes;
            }
        }
        if (!ctype_digit($window) || (int) $window < self::WINDOW_MIN || (int) $window > self::WINDOW_MAX) {
            $errors[] = t('The reply window must be between {min} and {max} hours.', ['min' => self::WINDOW_MIN, 'max' => self::WINDOW_MAX]);
        }
        if ($errors) {
            return [null, $errors];
        }
        return [['mode' => $mode, 'interval' => $interval, 'window' => (int) $window], []];
    }

    /** Save the settings under the lock. */
    public static function save(Store $store, array $settings): void
    {
        $store->locked(static function () use ($store, $settings): void {
            $config = $store->read('config');
            $config['trust'] = [
                'mode' => $settings['mode'],
                'interval' => $settings['interval'] === null ? 'auto' : $settings['interval'],
                'window' => $settings['window'],
            ];
            $store->write('config', $config);
        });
    }
}
