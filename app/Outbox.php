<?php
declare(strict_types=1);

namespace DPS;

/**
 * The one door every email leaves through.
 *
 * It never throws. It returns true or false, records the result where the
 * dashboard and the health check can see it, and writes a history line. The
 * engine relies on that return value: an escalation step only counts as
 * done when its email was accepted by the mail server.
 */
final class Outbox
{
    /** Whether the mail server accepted the email of the last send(). */
    public bool $emailOk = false;
    /** Seconds one process may spend on failing channels; later channels are skipped. */
    public float $channelBudget = 120.0;
    private float $channelSpent = 0.0;
    /** Seconds to wait between two parts of a long group message, so the platform does not refuse them as a flood. */
    public float $partPause = 1.0;
    /** @var null|callable(float): void how to wait; tests replace it */
    public $pause = null;

    public function __construct(private Store $store, private Transport $transport, private ?Notifier $notifier = null)
    {
    }

    /**
     * @param string|string[] $to
     * @param string $audience 'owner' or 'contact'; sets the From name and Reply-To
     * @param array|null $smtp Override the saved SMTP settings (used while testing new ones)
     * @param bool $channels False for sign-in codes: they never go to notification channels
     */
    public function send(string|array $to, Email $email, string $audience = 'owner', ?array $smtp = null, bool $channels = true): bool
    {
        $to = array_values(array_unique(array_filter(array_map('trim', (array) $to))));
        if ($to === []) {
            return false;
        }
        $config = $this->store->read('config');
        $useChannels = $channels && $smtp === null && $this->notifier !== null;
        $smtp ??= (array) ($config['smtp'] ?? []);
        $owner = trim((string) ($config['owner_name'] ?? ''));

        if ($audience === 'contact' && $owner !== '') {
            $fromName = $owner . ' via Death Plan Switch';
            $replyTo = (string) ($config['owner_email'] ?? '');
        } else {
            $fromName = 'Death Plan Switch';
            $replyTo = '';
        }

        try {
            $this->transport->send($smtp, $to, $fromName, $replyTo, $email);
            $this->record(true, $to, $email->subject, '');
            $ok = true;
        } catch (\Throwable $e) {
            $this->record(false, $to, $email->subject, $e->getMessage());
            $ok = false;
        }
        $this->emailOk = $ok;
        if ($useChannels) {
            $ok = $this->fanOut($config, $to, $email, $audience, $ok) || $ok;
        }
        return $ok;
    }

    /** Send the notice on every channel of these recipients. True if any got through. */
    private function fanOut(array $config, array $to, Email $email, string $audience, bool $emailSent): bool
    {
        $any = false;
        try {
            $contacts = (array) ($this->store->read('contacts')['contacts'] ?? []);
            $found = Channels::forAddresses($config, $contacts, $to);
            $notice = Notice::fromEmail($email, implode(', ', $to), $emailSent);
        } catch (\Throwable) {
            return false;
        }
        foreach ($found as $channel) {
            // One dead channel costs up to 10 s; many must not outlast the scheduler's 300 s limit.
            if ($this->channelSpent >= $this->channelBudget) {
                $this->recordChannel($channel, 'Skipped: too much time was already spent on slow channels in this run');
                continue;
            }
            $started = microtime(true);
            try {
                $this->notifier->notify($channel, $notice, $audience);
                $error = '';
                $any = true;
            } catch (\Throwable $e) {
                $error = $e->getMessage() !== '' ? $e->getMessage() : 'Unknown error';
                $this->channelSpent += microtime(true) - $started;
            }
            $this->recordChannel($channel, $error);
        }
        return $any;
    }

    /** Is there time left in this run for another channel? */
    public function hasChannelBudget(): bool
    {
        return $this->channelSpent < $this->channelBudget;
    }

    /**
     * Send one part of a group channel's message. Never throws. All the time it takes, the pause before it
     * included, counts against the channel budget, so a long message cannot outlast the run.
     *
     * @param bool $pauseFirst wait $partPause seconds first: a part has just gone to this channel
     * @return string '' when it got through, else the reason
     */
    public function sendPart(array $channel, string $text, bool $pauseFirst): string
    {
        if ($this->notifier === null) {
            return 'Notification channels are not set up';
        }
        $started = microtime(true);
        if ($pauseFirst && $this->partPause > 0) {
            $this->pause !== null ? ($this->pause)($this->partPause) : usleep((int) ($this->partPause * 1_000_000));
        }
        try {
            $this->notifier->notify($channel, new Notice('', $text, null), 'group');
            $error = '';
        } catch (\Throwable $e) {
            $error = $e->getMessage() !== '' ? $e->getMessage() : 'Unknown error';
        }
        $this->channelSpent += microtime(true) - $started;
        // The engine writes one history line for the whole message, not one per part.
        $this->recordChannel($channel, $error, false);
        return $error;
    }

    private function recordChannel(array $channel, string $error, bool $log = true): void
    {
        $id = (string) ($channel['id'] ?? '');
        $label = mb_substr((string) ($channel['label'] ?? ''), 0, 60);
        try {
            $this->store->locked(function () use ($id, $label, $error): void {
                $state = $this->store->read('mail');
                $row = (array) ($state['channels'][$id] ?? []);
                $row['label'] = $label;
                if ($error === '') {
                    $row['ok_at'] = Clock::now();
                } else {
                    $row['error'] = mb_substr($error, 0, 500);
                    $row['error_at'] = Clock::now();
                }
                $state['channels'][$id] = $row;
                $this->store->write('mail', $state);
            });
        } catch (\Throwable) {
            // Best effort, like record().
        }
        if (!$log) {
            return;
        }
        try {
            // Only the scheme: masked() would show a short address in full.
            $url = (string) ($channel['url'] ?? '');
            $where = $label . ' (' . (strpos($url, '://') !== false ? substr($url, 0, (int) strpos($url, '://')) . '://…' : '…') . ')';
            $this->store->log(
                $error === '' ? 'channel.sent' : 'channel.failed',
                $error === '' ? t('Notified {where}', ['where' => $where]) : t('Could not notify {where}.', ['where' => $where]) . ' ' . mb_substr($error, 0, 300)
            );
        } catch (\Throwable) {
        }
    }

    private function record(bool $ok, array $to, string $subject, string $error): void
    {
        try {
            $this->store->locked(function () use ($ok, $to, $subject, $error): void {
                $state = $this->store->read('mail');
                if ($ok) {
                    $state['last_send_ok_at'] = Clock::now();
                    $state['last_send_error'] = null;
                } else {
                    $state['last_send_error'] = [
                        'at' => Clock::now(),
                        'to' => implode(', ', $to),
                        'subject' => $subject,
                        'error' => mb_substr($error, 0, 500),
                    ];
                }
                $this->store->write('mail', $state);
            });
        } catch (\Throwable) {
            // Recording is best effort; the caller still gets the true result.
        }
        $this->store->log(
            $ok ? 'mail.sent' : 'mail.failed',
            ($ok ? t('Sent "{subject}" to {to}', ['subject' => $subject, 'to' => implode(', ', $to)]) : t('Could not send "{subject}" to {to}', ['subject' => $subject, 'to' => implode(', ', $to)]))
                . ($ok ? '' : '. ' . mb_substr($error, 0, 300))
        );
    }
}
