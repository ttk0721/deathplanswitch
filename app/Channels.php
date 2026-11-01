<?php
declare(strict_types=1);

namespace DPS;

/**
 * Notification channels: validation, display and lookup by recipient.
 *
 * A channel is {id, type: apprise|webhook, url, label, secret, created_at}.
 * SMS channels are not stored; they are built from the SMS template and the
 * person's phone number each time.
 */
final class Channels
{
    private const URL_MAX = 2000;
    private const LABEL_MAX = 60;

    public static function normalize(array $raw): ?array
    {
        $type = (string) ($raw['type'] ?? '');
        $url = trim((string) ($raw['url'] ?? ''));
        if ($url === '' || strlen($url) > self::URL_MAX || preg_match('/\s/u', $url) === 1) {
            return null;
        }
        if ($type === 'apprise') {
            if (preg_match('/^([a-z][a-z0-9+.-]*):\/\/\S+$/', $url, $m) !== 1 || in_array($m[1], ['http', 'https'], true)) {
                return null;
            }
            $default = $m[1];
            $secret = null;
        } elseif ($type === 'webhook') {
            if (preg_match('/^https?:\/\//i', $url) !== 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
                return null;
            }
            $default = 'webhook';
            $secret = bin2hex(random_bytes(16));
        } else {
            return null;
        }
        $label = trim(str_replace(["\r", "\n", "\0"], ' ', (string) ($raw['label'] ?? '')));
        $label = mb_substr($label, 0, self::LABEL_MAX);
        return [
            'id' => 'ch_' . bin2hex(random_bytes(5)),
            'type' => $type,
            'url' => $url,
            'label' => $label !== '' ? $label : $default,
            'secret' => $secret,
            'created_at' => Clock::now(),
        ];
    }

    public static function fromPost(): ?array
    {
        return self::normalize([
            'type' => Web::post('channel_type'),
            'url' => Web::post('channel_url'),
            'label' => Web::post('channel_label'),
        ]);
    }

    /** Scheme, "://", the first 4 characters of the rest, and an ellipsis. */
    public static function masked(array $channel): string
    {
        $url = (string) ($channel['url'] ?? '');
        $pos = strpos($url, '://');
        if ($pos === false) {
            return '…';
        }
        return substr($url, 0, $pos + 3) . mb_substr(substr($url, $pos + 3), 0, 4) . '…';
    }

    public static function phone(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }
        return (str_starts_with(trim($raw), '+') ? '+' : '') . $digits;
    }

    /**
     * Channels for the given email addresses, without duplicates, in order.
     *
     * @param array<int, array> $contacts
     * @param array<int, string> $addresses
     * @return array<int, array>
     */
    public static function forAddresses(array $config, array $contacts, array $addresses): array
    {
        $out = [];
        $seen = [];
        $add = function (array $channel) use (&$out, &$seen): void {
            $id = (string) ($channel['id'] ?? '');
            if ($id !== '' && !isset($seen[$id])) {
                $seen[$id] = true;
                $out[] = $channel;
            }
        };
        $same = static fn (string $a, string $b): bool => $a !== '' && strcasecmp($a, $b) === 0;
        $template = (string) ($config['sms_template'] ?? '');

        foreach ($addresses as $address) {
            $address = trim((string) $address);
            if ($same((string) ($config['owner_email'] ?? ''), $address) || $same((string) ($config['backup_email'] ?? ''), $address)) {
                foreach ((array) ($config['channels'] ?? []) as $channel) {
                    $add($channel);
                }
                $sms = self::sms('sms_owner', (string) ($config['owner_phone'] ?? ''), $template);
                if ($sms !== null) {
                    $add($sms);
                }
            }
            foreach ($contacts as $contact) {
                if (!$same((string) ($contact['email'] ?? ''), $address)) {
                    continue;
                }
                foreach ((array) ($contact['channels'] ?? []) as $channel) {
                    $add($channel);
                }
                $sms = self::sms('sms_' . (string) ($contact['id'] ?? ''), (string) ($contact['phone'] ?? ''), $template);
                if ($sms !== null) {
                    $add($sms);
                }
            }
        }
        return $out;
    }

    private static function sms(string $id, string $phone, string $template): ?array
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if ($digits === '' || !str_contains($template, '{phone}')) {
            return null;
        }
        return [
            'id' => $id,
            'type' => 'apprise',
            'url' => str_replace('{phone}', $digits, $template),
            'label' => 'SMS',
            'secret' => null,
        ];
    }
}
