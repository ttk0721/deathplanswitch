<?php
declare(strict_types=1);

namespace DPS;

/**
 * Sends a Notice to an Apprise sidecar or to a signed webhook.
 *
 * Errors never carry the channel address: it holds tokens.
 */
final class HttpNotifier implements Notifier
{
    public function __construct(private string $appriseUrl)
    {
    }

    public static function signature(string $body, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, $secret);
    }

    public function notify(array $channel, Notice $notice, string $event): void
    {
        $label = (string) ($channel['label'] ?? 'channel');
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

        if (($channel['type'] ?? '') === 'webhook') {
            $url = (string) ($channel['url'] ?? '');
            $body = json_encode([
                'event' => $event,
                'title' => $notice->title,
                'body' => $notice->body,
                'link' => $notice->link,
                'sent_at' => Clock::now(),
            ], $flags);
            $headers = [
                'Content-Type: application/json',
                'X-DPS-Event: ' . $event,
                'X-DPS-Signature: ' . self::signature($body, (string) ($channel['secret'] ?? '')),
            ];
        } else {
            if ($this->appriseUrl === '') {
                throw new \RuntimeException('Apprise is not set up');
            }
            $url = rtrim($this->appriseUrl, '/') . '/notify/';
            $body = json_encode([
                'urls' => (string) ($channel['url'] ?? ''),
                'title' => $notice->title,
                'body' => $notice->text(),
                'type' => 'info',
            ], $flags);
            $headers = ['Content-Type: application/json'];
        }

        $this->post($label, $url, $body, $headers);
    }

    /** @param string[] $headers */
    private function post(string $label, string $url, string $body, array $headers): void
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException("{$label}: could not start the request");
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($ok === false) {
            // curl's own text can quote the address, so keep only the reason.
            $reason = trim(str_replace($url, '', $error));
            throw new \RuntimeException("{$label}: " . ($reason !== '' ? $reason : 'request failed'));
        }
        if ($code < 200 || $code > 299) {
            throw new \RuntimeException("{$label}: HTTP {$code}");
        }
    }
}
