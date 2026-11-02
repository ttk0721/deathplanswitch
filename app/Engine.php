<?php
declare(strict_types=1);

namespace DPS;

/**
 * The switch itself: the timer, the escalation, and everything that changes
 * the switch's state.
 *
 * How it keeps time
 * -----------------
 * Nothing here depends on running at a particular moment. tick() is called
 * every 15 minutes or so (by cron, or by the secret URL), looks at the stored
 * timestamps, and does whatever has fallen due. If the server is off for a
 * day, the next tick simply picks up where things stood: late, never wrong.
 *
 * How it escalates
 * ----------------
 * One "cycle" runs per weekly check-in email:
 *
 *   checkin  the owner has been emailed and reminded       (72 hours)
 *   verify   the next of kin has been asked                (72 hours)
 *   fired    every confirmed contact has their message
 *
 * A step only counts once its email has been accepted by the mail server,
 * and steps are never skipped or bunched together. So a broken mail server
 * or a stopped cron job delays the escalation; it cannot cause one.
 *
 * The owner can check in at any stage. Anyone already contacted is then
 * told it was a false alarm.
 */
final class Engine
{
    private Contacts $contacts;
    /** @var callable(Store, string): array */
    private $prober;

    public function __construct(private Store $store, private Outbox $outbox, ?callable $prober = null, private ?Coordinator $coordinator = null)
    {
        $this->contacts = new Contacts($store);
        $this->prober = $prober ?? [Probe::class, 'run'];
    }

    // ------------------------------------------------------------------
    // Reading
    // ------------------------------------------------------------------

    public function config(): array
    {
        return $this->store->read('config');
    }

    public function state(): array
    {
        return $this->store->read('state') + [
            'armed' => false,
            'armed_at' => null,
            'paused_until' => null,
            'last_checkin_at' => null,
            'last_checkin_via' => null,
            'next_due_at' => null,
            'cycle' => null,
            'first_tick_at' => null,
            'last_tick_at' => null,
            'last_tick_source' => null,
            'probe' => null,
            'review_at' => null,
            'review_asked_at' => null,
            'after_fire_at' => null,
            'all_clear_queue' => [],
            'trusted_clear_queue' => [],
        ];
    }

    public function contacts(): Contacts
    {
        return $this->contacts;
    }

    public function installed(): bool
    {
        return !empty($this->config()['installed_at']);
    }

    // ------------------------------------------------------------------
    // The scheduler
    // ------------------------------------------------------------------

    /**
     * Do whatever is due. Safe to call as often as you like.
     *
     * @param string $source 'cron' or 'url'
     * @return string[] a plain description of what happened, for logs
     */
    public function tick(string $source = 'cron'): array
    {
        if (!$this->store->exists('config')) {
            return ['Not installed yet.'];
        }
        return $this->store->locked(function () use ($source): array {
            $now = Clock::now();
            $did = [];

            $state = $this->state();
            $state['first_tick_at'] ??= $now;
            $state['last_tick_at'] = $now;
            $state['last_tick_source'] = $source;
            $this->saveState($state);

            $config = $this->config();
            if (empty($config['installed_at'])) {
                return ['Install not finished. The run was recorded.'];
            }

            // News from peers comes first: never ask or fire on a cycle a peer has already closed.
            if ($this->federated()) {
                foreach ($this->applyRemote() as $line) {
                    $did[] = $line;
                }
                $state = $this->state();
            }

            $this->housekeeping($state, $config, $now, $did);

            if ($state['paused_until'] !== null && $now >= $state['paused_until']) {
                $state['paused_until'] = null;
                $state['next_due_at'] = $this->nextSlot($config, $now);
                $this->saveState($state);
                $this->store->log('pause.ended', t('The pause ended. The switch is active again.'));
                $this->outbox->send($this->ownerAddresses($config), Messages::ownerPauseEnded($config, $state['next_due_at']));
                $did[] = 'Pause ended.';
            }

            if ($state['armed'] && $state['paused_until'] === null) {
                $this->advance($state, $config, $now, $did);
            }

            $this->saveState($state);
            return $did ?: ['Nothing due.'];
        });
    }

    /** Move the current cycle forward by at most one step. */
    private function advance(array &$state, array $config, int $now, array &$did): void
    {
        if ($state['cycle'] === null) {
            $state['next_due_at'] ??= $this->nextSlot($config, $now);
            if ($now < $state['next_due_at']) {
                return;
            }
            if ($this->federated()) {
                $key = (int) $state['next_due_at'];
                $peer = $this->coordinator->peerDid($key, 'checkin');
                if ($peer !== null) {
                    // The local timeline starts no earlier than the local due time, whatever the peer claims.
                    $this->adoptCycle($state, max($peer, $key), $did);
                    return;
                }
                if (!$this->coordinator->mayAct($key, 'checkin', $key, $now)) {
                    return;
                }
            }
            $this->startCycle($state, $config, $now, $did);
            return;
        }

        $plan = self::plan($state['cycle'], null);
        $key = (int) ($state['cycle']['due_at'] ?? 0);

        switch ($state['cycle']['stage']) {
            case 'checkin':
                foreach (array_keys(Schedule::OWNER_REMINDERS) as $i) {
                    $n = $i + 1;
                    $step = $plan['reminder' . $n];
                    if (!$step['done']) {
                        $peer = $this->peerDid($key, 'reminder' . $n, $step['at'] - self::CLOCK_SKEW);
                        if ($peer !== null) {
                            $state['cycle']['reminders'][] = $peer;
                            $state['cycle']['steps']['reminder' . $n] = $peer;
                            $this->saveState($state);
                            $did[] = "Reminder {$n} was already sent by another node.";
                        } elseif ($now >= $step['at'] && $this->may($key, 'reminder' . $n, $step['at'], $now)) {
                            $this->remindOwner($state, $config, $n, $did);
                        }
                        return;
                    }
                }
                $verifyAt = $plan['verify']['at'] + $this->extraDelay('verify', $now);
                $peer = $this->peerDid($key, 'verify', $verifyAt - self::CLOCK_SKEW);
                if ($peer !== null) {
                    $state['cycle']['stage'] = 'verify';
                    $state['cycle']['verify'] = $this->newVerify($peer, false, $this->trustDescriptor($config), false);
                    $state['cycle']['steps']['verify'] = $peer;
                    $this->saveState($state);
                    $this->mergePeerTrust($state);
                    $did[] = 'The next of kin was already asked by another node.';
                } elseif ($now >= $verifyAt && $this->may($key, 'verify', $verifyAt, $now)) {
                    $this->askNextOfKin($state, $config, $now, $did);
                }
                return;

            case 'verify':
                // The trusted people after the first, each asked once.
                $asked = $this->advanceAsks($state, $config, $now, $did);
                $this->keepVerifyTokensLive($state);
                if ($asked) {
                    return;
                }
                $plan = self::plan($state['cycle'], null);
                if (!$plan['nok_reminder']['done']) {
                    $peer = $this->peerDid($key, 'nok_reminder', $plan['nok_reminder']['at'] - self::CLOCK_SKEW);
                    if ($peer !== null) {
                        $state['cycle']['verify']['reminded_at'] = $peer;
                        $state['cycle']['steps']['nok_reminder'] = $peer;
                        $this->saveState($state);
                        $did[] = 'The next of kin was already reminded by another node.';
                    } elseif ($now >= $plan['nok_reminder']['at'] && $this->may($key, 'nok_reminder', $plan['nok_reminder']['at'], $now)) {
                        $this->remindNextOfKin($state, $config, $now, $did);
                    }
                    return;
                }
                // Never before the last trusted person has been asked.
                if (self::pendingAsks($plan) !== []) {
                    return;
                }
                $fireAt = $plan['fire']['at'] + $this->extraDelay('fire', $now);
                $peer = $this->peerFire($state['cycle'], $now);
                if ($peer !== null) {
                    $reason = (string) ($this->coordinator->peerCycle($key)['fired_reason'] ?? 'timeout');
                    $this->adoptFire($state, $peer, $reason, $did);
                    $this->deliverFired($state, $config, $did);
                } elseif ($now >= $fireAt && $this->may($key, 'fire', $fireAt, $now)) {
                    $this->fire($state, $config, 'timeout', $did);
                }
                return;

            case 'fired':
                $this->deliverFired($state, $config, $did);
                return;
        }
    }

    private function startCycle(array &$state, array $config, int $now, array &$did): void
    {
        [$token, $record] = Tokens::issue(Schedule::CHECKIN_TOKEN_TTL);
        $email = Messages::checkin(
            $config,
            $this->url($config, 'checkin.php', ['t' => $token]),
            0,
            $now + Schedule::OWNER_WINDOW,
            $this->contacts->nok(),
            array_column($this->warnings(), 'text')
        );
        // The clock for this cycle starts when the email is accepted, not when it was due.
        if (!$this->outbox->send((string) $config['owner_email'], $email)) {
            $did[] = 'Could not send the check-in email. Will try again on the next run.';
            return;
        }
        $state['cycle'] = [
            'id' => gmdate('Ymd-His', $now),
            'due_at' => $state['next_due_at'],
            'started_at' => $now,
            'stage' => 'checkin',
            'tokens' => [$record],
            'reminders' => [],
            'verify' => null,
            'fired_at' => null,
            'fired_reason' => null,
            'notified' => [],
            'owner_told_fired_at' => null,
            'steps' => ['checkin' => $now],
        ];
        $this->saveState($state);
        $this->store->log('cycle.started', t('Weekly check-in email sent.'));
        $did[] = 'Sent the weekly check-in email.';
    }

    private function remindOwner(array &$state, array $config, int $number, array &$did): void
    {
        [$token, $record] = Tokens::issue(Schedule::CHECKIN_TOKEN_TTL);
        $plan = self::plan($state['cycle'], null);
        $email = Messages::checkin(
            $config,
            $this->url($config, 'checkin.php', ['t' => $token]),
            $number,
            $plan['verify']['at'],
            $this->contacts->nok(),
            array_column($this->warnings(), 'text')
        );
        if (!$this->outbox->send($this->ownerAddresses($config), $email)) {
            $did[] = "Could not send reminder {$number}. Will try again on the next run.";
            return;
        }
        $state['cycle']['tokens'][] = $record;
        $state['cycle']['reminders'][] = Clock::now();
        $state['cycle']['steps']['reminder' . $number] = Clock::now();
        $this->saveState($state);
        $this->store->log('cycle.reminder', t('Reminder {number} sent to the owner.', ['number' => $number]));
        $did[] = "Sent reminder {$number} to the owner.";
    }

    private function askNextOfKin(array &$state, array $config, int $now, array &$did): void
    {
        $desc = $this->trustDescriptor($config);
        $first = $desc['chain'] === [] ? null : $this->contacts->find($desc['chain'][0]);
        $verifyTokens = [];
        $reached = false;

        // The deadline in the email is the real one: the last trusted person's turn, plus the reply window.
        $draft = $state['cycle'];
        $draft['verify'] = $this->newVerify($now, false, $desc, true);
        $fireAt = self::plan($draft, null)['fire']['at'];

        if ($first !== null) {
            [$token, $record] = Tokens::issue(max(Schedule::NOK_WINDOW + 30 * Schedule::DAY, $fireAt + self::VERIFY_TOKEN_GRACE - $now));
            $verifyTokens[] = $record + ['contact' => (string) $first['id']];
            $reached = $this->outbox->send(
                (string) $first['email'],
                $this->verifyEmail($config, $state, $first, $token, $fireAt, false),
                'contact'
            );
        }

        // The owner is told too, with one more chance to check in.
        [$ownerToken, $ownerRecord] = Tokens::issue(Schedule::CHECKIN_TOKEN_TTL);
        $ownerTold = $this->outbox->send(
            $this->ownerAddresses($config),
            Messages::ownerVerifyStarted($config, $this->url($config, 'checkin.php', ['t' => $ownerToken]), $first, $fireAt)
        );

        if (!$reached && !$ownerTold) {
            // No email is getting out at all. Wait for mail to work rather than escalate unseen.
            $did[] = 'Could not send any email. The escalation is waiting for mail to work.';
            return;
        }

        $state['cycle']['tokens'][] = $ownerRecord;
        $state['cycle']['stage'] = 'verify';
        $state['cycle']['verify'] = $this->newVerify($now, $reached, $desc, true);
        $state['cycle']['verify']['tokens'] = $verifyTokens;
        $state['cycle']['steps']['verify'] = $now;
        $this->saveState($state);
        $this->store->log('cycle.verify', $reached
            ? t('No check-in after 72 hours. The next of kin has been asked.')
            : t('No check-in after 72 hours. The next of kin could not be emailed; the 72-hour wait has started anyway.'));
        $did[] = $reached ? 'Asked the next of kin.' : 'Could not email the next of kin. Waiting 72 hours before notifying the others.';
    }

    /**
     * The people who will be asked, and how: the confirmed trusted people in order, and the timing
     * from Settings. Fixed for the cycle once the first person is asked.
     *
     * @return array{chain: string[], mode: string, gap: int, window: int}
     */
    private function trustDescriptor(array $config): array
    {
        $ids = array_map(static fn (array $c): string => (string) $c['id'], $this->contacts->trustedConfirmed());
        return ['chain' => $ids] + Trust::timing(Trust::settings($config), count($ids));
    }

    /** The verify block of a cycle in which the first trusted person has just been asked. */
    private function newVerify(int $askedAt, bool $reached, array $desc, bool $own): array
    {
        $first = $desc['chain'][0] ?? null;
        return [
            'asked_at' => $askedAt,
            'reached' => $reached,
            'reminded_at' => null,
            'tokens' => [],
            'response' => null,
            'responded_at' => null,
            'chain' => $desc['chain'],
            'mode' => $desc['mode'],
            'gap' => $desc['gap'],
            'window' => $desc['window'],
            'asks' => $first === null ? [] : [$first => ['asked_at' => $askedAt, 'reached' => $reached, 'own' => $own]],
        ];
    }

    /** How long after the fire time an answer link still works while the cycle waits. */
    private const VERIFY_TOKEN_GRACE = 7 * Schedule::DAY;

    /**
     * Keep every answer link of a waiting cycle valid until at least its fire time plus a week. The fire time
     * moves later as people are asked, so this runs whenever the cycle moves. Saves only when something changed.
     */
    private function keepVerifyTokensLive(array &$state): void
    {
        if (($state['cycle']['stage'] ?? null) !== 'verify' || !is_array($state['cycle']['verify'] ?? null)) {
            return;
        }
        $until = self::plan($state['cycle'], null)['fire']['at'] + self::VERIFY_TOKEN_GRACE;
        $changed = false;
        foreach ((array) ($state['cycle']['verify']['tokens'] ?? []) as $i => $record) {
            if (is_array($record) && (int) ($record['expires_at'] ?? 0) < $until) {
                $state['cycle']['verify']['tokens'][$i]['expires_at'] = $until;
                $changed = true;
            }
        }
        if ($changed) {
            $this->saveState($state);
        }
    }

    /** The chain and timing a cycle runs on, as shown to peers. */
    private static function trustOf(array $verify): array
    {
        return [
            'chain' => array_values(array_map('strval', (array) ($verify['chain'] ?? []))),
            'mode' => ($verify['mode'] ?? '') === 'all_at_once' ? 'all_at_once' : 'sequential',
            'gap' => (int) ($verify['gap'] ?? 0),
            'window' => (int) ($verify['window'] ?? 0),
        ];
    }

    /**
     * Widen this cycle's chain and timing with what peers run on, so every node waits for the latest fire time:
     * everyone on any node's chain (this node's order first, then the others in theirs), the longest interval,
     * the longest reply window, and one at a time when any node asks one at a time. Nothing is ever narrowed.
     *
     * @return bool true when anything changed
     */
    private function mergePeerTrust(array &$state): bool
    {
        if (!$this->federated() || !isset($state['cycle']['verify']['chain'])) {
            return false;
        }
        $mine = self::trustOf((array) $state['cycle']['verify']);
        $merged = $mine;
        foreach ($this->coordinator->peerTrusts((int) ($state['cycle']['due_at'] ?? 0)) as $t) {
            $chain = is_array($t['chain'] ?? null) ? array_values(array_filter($t['chain'], static fn ($id): bool =>
                is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $id) === 1)) : [];
            foreach (array_slice($chain, 0, 100) as $id) {
                if (!in_array($id, $merged['chain'], true)) {
                    $merged['chain'][] = $id;
                }
            }
            if (is_int($t['gap'] ?? null)) {
                $merged['gap'] = max($merged['gap'], min($t['gap'], max(Trust::INTERVAL_MAX * 60, Trust::AUTO_SPREAD)));
            }
            if (is_int($t['window'] ?? null)) {
                $merged['window'] = max($merged['window'], min($t['window'], Trust::WINDOW_MAX * Schedule::HOUR));
            }
            if (($t['mode'] ?? null) === 'sequential') {
                $merged['mode'] = 'sequential';
            }
        }
        if ($merged === $mine) {
            return false;
        }
        $state['cycle']['verify'] = $merged + $state['cycle']['verify'];
        $this->saveState($state);
        $this->keepVerifyTokensLive($state);
        return true;
    }

    /** @return array<string, array> the asks in a plan that have not happened yet, by step name */
    private static function pendingAsks(array $plan): array
    {
        return array_filter($plan, static fn (array $step, string $name): bool => str_starts_with($name, 'ask:') && !$step['done'], ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Ask the next trusted people who are due. Each is asked once: if a peer node asked them, that is taken in,
     * but never earlier than this node's own schedule allows.
     *
     * @return bool true when anything was done (the tick then ends)
     */
    private function advanceAsks(array &$state, array $config, int $now, array &$did): bool
    {
        $key = (int) ($state['cycle']['due_at'] ?? 0);
        $acted = false;
        $limit = count((array) ($state['cycle']['verify']['chain'] ?? [])) + 1;
        for ($i = 0; $i < $limit; $i++) {
            $pending = self::pendingAsks(self::plan($state['cycle'], null));
            if ($pending === []) {
                return $acted;
            }
            $name = (string) array_key_first($pending);
            $step = $pending[$name];
            $id = (string) $step['id'];
            // A peer's ask is only taken in near this node's own time for it, and never dated earlier than that:
            // a peer with a fast clock must not pull the later asks, or the fire time, forward.
            $peer = $now >= $step['at'] - self::CLOCK_SKEW ? $this->peerDid($key, $name, $step['at'] - self::CLOCK_SKEW) : null;
            if ($peer !== null) {
                $adopted = max((int) $step['at'], min($peer, $now));
                $state['cycle']['verify']['asks'][$id] = ['asked_at' => $adopted, 'reached' => false, 'own' => false];
                $state['cycle']['steps'][$name] = $adopted;
                $this->saveState($state);
                $did[] = 'A trusted person was already asked by another node.';
                $acted = true;
                continue;
            }
            if ($now < $step['at'] || !$this->may($key, $name, $step['at'], $now)) {
                return $acted;
            }
            if (!$this->askTrusted($state, $config, $now, $id, $step['at'], $did)) {
                return $acted;
            }
            $acted = true;
            if (($state['cycle']['verify']['mode'] ?? 'sequential') !== 'all_at_once') {
                return true; // one at a time
            }
        }
        return $acted;
    }

    /** @return bool false when the email failed and the ask will be tried again on the next run */
    private function askTrusted(array &$state, array $config, int $now, string $id, int $plannedAt, array &$did): bool
    {
        $contact = $this->contacts->find($id);
        $sent = false;
        if ($contact !== null && ($contact['status'] ?? '') === 'confirmed') {
            $draft = $state['cycle'];
            $draft['verify']['asks'][$id] = ['asked_at' => $now, 'reached' => true, 'own' => true];
            $fireAt = self::plan($draft, null)['fire']['at'];
            [$token, $record] = Tokens::issue(max(Schedule::NOK_WINDOW + 30 * Schedule::DAY, $fireAt + self::VERIFY_TOKEN_GRACE - $now));
            $sent = $this->outbox->send(
                (string) $contact['email'],
                $this->verifyEmail($config, $state, $contact, $token, $fireAt, false),
                'contact'
            );
            if ($sent) {
                $state['cycle']['verify']['tokens'][] = $record + ['contact' => $id];
            } elseif ($now < $plannedAt + Schedule::MIN_STEP_GAP) {
                $did[] = "Could not email {$contact['name']}. Will try again on the next run.";
                return false;
            }
        }
        // After a long failure the person counts as asked, so the others are not held up for ever.
        $state['cycle']['verify']['asks'][$id] = ['asked_at' => $now, 'reached' => $sent, 'own' => true];
        $state['cycle']['steps']['ask:' . $id] = $now;
        $this->saveState($state);
        $name = $contact['name'] ?? $id;
        $this->store->log('cycle.trusted_asked', $sent
            ? t('{name} was asked whether {owner} is all right.', ['name' => $name, 'owner' => (string) ($config['owner_name'] ?? '')])
            : t('{name} could not be emailed.', ['name' => $name]));
        $did[] = $sent ? "Asked {$name}." : "Could not email {$name}.";
        return true;
    }

    private function remindNextOfKin(array &$state, array $config, int $now, array &$did): void
    {
        $firstId = $state['cycle']['verify']['chain'][0] ?? null;
        $nok = is_string($firstId) ? $this->contacts->find($firstId) : $this->confirmedNok();
        $plan = self::plan($state['cycle'], null);
        $fireAt = max($plan['fire']['at'], $now + Schedule::MIN_STEP_GAP);

        if ($nok !== null) {
            [$token, $record] = Tokens::issue(max(Schedule::NOK_WINDOW + 30 * Schedule::DAY, $fireAt + self::VERIFY_TOKEN_GRACE - $now));
            $state['cycle']['verify']['tokens'][] = $record + ['contact' => (string) $nok['id']];
            $sent = $this->outbox->send(
                (string) $nok['email'],
                $this->verifyEmail($config, $state, $nok, $token, $fireAt, true),
                'contact'
            );
            if ($sent) {
                $state['cycle']['verify']['reached'] = true;
                if (isset($state['cycle']['verify']['asks'][$nok['id']])) {
                    $state['cycle']['verify']['asks'][$nok['id']]['reached'] = true;
                }
            }
        }
        // Best effort: a next of kin who cannot be emailed must not hold the switch up for ever.
        $state['cycle']['verify']['reminded_at'] = $now;
        $state['cycle']['steps']['nok_reminder'] = $now;
        $this->saveState($state);
        $this->store->log('cycle.verify_reminder', t('The next of kin was reminded.'));
        $did[] = 'Reminded the next of kin.';
    }

    /** @param string $reason 'timeout' or 'nok_wrong' */
    private function fire(array &$state, array $config, string $reason, array &$did): void
    {
        $state['cycle']['stage'] = 'fired';
        $state['cycle']['fired_at'] = Clock::now();
        $state['cycle']['fired_reason'] = $reason;
        $state['cycle']['steps']['fire'] = $state['cycle']['fired_at'];
        $this->saveState($state);
        $this->store->log('cycle.fired', $reason === 'nok_wrong'
            ? t('The next of kin answered "Something\'s wrong". The switch has fired.')
            : t('Neither the owner nor the next of kin confirmed all is well. The switch has fired.'));
        $did[] = 'The switch fired.';
        $this->deliverFired($state, $config, $did);
    }

    /** Send each confirmed contact their message, next of kin first. Retries anyone not yet reached. */
    private function deliverFired(array &$state, array $config, array &$did): void
    {
        $all = $this->contacts->all();
        $vaultContacts = array_filter(array_column((new Vault($this->store))->read()['recipients'], 'contact_id'), 'is_string');
        foreach ($this->contacts->confirmed() as $contact) {
            if (isset($state['cycle']['notified'][$contact['id']])) {
                continue;
            }
            if ($this->federated()) {
                $key = (int) ($state['cycle']['due_at'] ?? 0);
                $step = 'deliver:' . $contact['id'];
                $peer = $this->coordinator->peerDid($key, $step, $this->deliverFloor($state['cycle']));
                if ($peer !== null) {
                    $state['cycle']['notified'][$contact['id']] = $peer;
                    $state['cycle']['peer_told'][] = (string) $contact['id'];
                    $state['cycle']['steps'][$step] = $peer;
                    $this->saveState($state);
                    $did[] = "{$contact['name']} was already sent their message by another node.";
                    continue;
                }
                if (!$this->coordinator->mayAct($key, $step, (int) ($state['cycle']['fired_at'] ?? 0), Clock::now())) {
                    continue;
                }
            }
            $vars = Messages::vars($config, $state, $all, $contact);
            $fired = Messages::fired($config, $contact, $vars);
            if (in_array((string) $contact['id'], $vaultContacts, true)) {
                $fired->link(t('Open the vault'), $this->url($config, 'vault.php'));
            }
            // A channel notice only points to the email. Until the email itself is accepted the
            // contact is not "notified": a channel alone never carries the message.
            $told = isset($state['cycle']['channel_told'][$contact['id']]);
            $sent = $this->outbox->send((string) $contact['email'], $fired, 'contact', null, !$told);
            if ($sent && !$this->outbox->emailOk) {
                $state['cycle']['channel_told'][$contact['id']] = Clock::now();
                $this->saveState($state);
                $did[] = "Could not email {$contact['name']} their message; a notice went to their channels. Will try again on the next run.";
                continue;
            }
            if ($sent) {
                $state['cycle']['notified'][$contact['id']] = Clock::now();
                $state['cycle']['steps']['deliver:' . $contact['id']] = Clock::now();
                $state['cycle']['own_notified'] = true;
                $this->saveState($state);
                $this->store->log('cycle.notified', t('{role} ({name}) was sent their message.', ['role' => Contacts::label((string) $contact['role_label']), 'name' => $contact['name']]));
                $did[] = "Sent {$contact['name']} their message.";
            } else {
                $did[] = "Could not send {$contact['name']} their message. Will try again on the next run.";
            }
        }

        // A node that only adopted deliveries leaves the owner notice to the node that sent them.
        $ownerNoticeMine = !$this->federated() || !empty($state['cycle']['own_notified']);
        if ($state['cycle']['owner_told_fired_at'] === null && $state['cycle']['notified'] !== [] && $ownerNoticeMine) {
            [$token, $record] = Tokens::issue(Schedule::ALL_CLEAR_TOKEN_TTL);
            $names = [];
            foreach ($all as $contact) {
                if (isset($state['cycle']['notified'][$contact['id']])) {
                    $names[] = $contact['name'];
                }
            }
            $state['cycle']['tokens'][] = $record;
            if ($this->outbox->send(
                $this->ownerAddresses($config),
                Messages::ownerFired($this->url($config, 'checkin.php', ['t' => $token]), $names)
            )) {
                $state['cycle']['owner_told_fired_at'] = Clock::now();
            }
            $this->saveState($state);
        }
    }

    /**
     * End the current cycle because the owner is evidently here.
     * Anyone already contacted is told it was a false alarm.
     *
     * @return string[] names of the people told
     */
    private function closeCycle(array &$state, array $config): array
    {
        $cycle = $state['cycle'];
        if ($cycle === null) {
            return [];
        }
        $toTell = [];
        foreach ($this->askedIds($cycle) as $id) {
            $toTell[$id] = true;
        }
        foreach (array_keys($cycle['notified'] ?? []) as $id) {
            if (!in_array((string) $id, (array) ($cycle['peer_told'] ?? []), true)) {
                $toTell[$id] = true;
            }
        }

        $state['cycle'] = null;
        if ($cycle['stage'] === 'fired') {
            $state['after_fire_at'] = Clock::now();
        }
        $state['all_clear_queue'] = array_values(array_unique(array_merge(
            (array) $state['all_clear_queue'],
            array_map('strval', array_keys($toTell))
        )));
        $this->saveState($state);

        return $this->sendAllClears($state, $config);
    }

    /** @return string[] names told this time */
    private function sendAllClears(array &$state, array $config): array
    {
        $told = [];
        $remaining = [];
        foreach ((array) $state['all_clear_queue'] as $id) {
            $contact = $this->contacts->find((string) $id);
            if ($contact === null) {
                continue;
            }
            if ($this->outbox->send((string) $contact['email'], Messages::allClear($config), 'contact')) {
                $told[] = $contact['name'];
                $this->store->log('cycle.all_clear', t('{name} was told it was a false alarm.', ['name' => $contact['name']]));
            } else {
                $remaining[] = $id; // retried on the next run
            }
        }
        $state['all_clear_queue'] = $remaining;
        // People asked while another trusted person said "fine": told so, never that the owner checked in.
        $left = [];
        foreach ((array) ($state['trusted_clear_queue'] ?? []) as $id => $by) {
            $contact = $this->contacts->find((string) $id);
            if ($contact === null) {
                continue;
            }
            if ($this->outbox->send((string) $contact['email'], Messages::trustedClear($config, (string) $by), 'contact')) {
                $told[] = $contact['name'];
                $this->store->log('cycle.all_clear', t('{name} was told it was a false alarm.', ['name' => $contact['name']]));
            } else {
                $left[$id] = $by;
            }
        }
        $state['trusted_clear_queue'] = $left;
        $this->saveState($state);
        return $told;
    }

    /** Jobs that are not part of a cycle. */
    private function housekeeping(array &$state, array $config, int $now, array &$did): void
    {
        // False-alarm notices that could not be sent earlier.
        if ($state['all_clear_queue'] !== [] || $state['trusted_clear_queue'] !== []) {
            $told = $this->sendAllClears($state, $config);
            if ($told) {
                $did[] = 'Sent false-alarm notices to ' . implode(', ', $told) . '.';
            }
        }

        // Re-test the data folder once a day.
        $probe = $state['probe'];
        // A check made from the owner's browser stands in when the server cannot
        // reach itself, but only for 30 days: server settings change.
        $browserVerified = ($probe['result'] ?? '') === 'unknown'
            && !empty($probe['browser_ok_at'])
            && $probe['browser_ok_at'] + 30 * Schedule::DAY > $now;
        if (($probe['checked_at'] ?? 0) + Schedule::PROBE_EVERY <= $now) {
            $result = ($this->prober)($this->store, (string) ($config['base_url'] ?? ''));
            if ($result['result'] === 'unknown' && $browserVerified) {
                $result['browser_ok_at'] = $probe['browser_ok_at'];
            }
            $result['alerted_at'] = $probe['alerted_at'] ?? null;
            if ($result['result'] === 'exposed' && $result['alerted_at'] === null) {
                $this->outbox->send($this->ownerAddresses($config), Messages::ownerDataExposed($this->url($config, 'index.php')));
                $result['alerted_at'] = $now;
                $this->store->log('probe.exposed', t('The data folder can be read from the web.'));
                $did[] = 'Warning sent: the data folder can be read from the web.';
            } elseif ($result['result'] !== 'exposed') {
                $result['alerted_at'] = null;
            }
            $state['probe'] = $result;
            $this->saveState($state);
        }

        // Yearly: ask each contact to reconfirm.
        foreach ($this->contacts->confirmed() as $contact) {
            $since = (int) ($contact['reconfirmed_at'] ?? $contact['confirmed_at'] ?? 0);
            if ($since === 0 || $now < $since + Schedule::YEAR) {
                continue;
            }
            $asked = $contact['reconfirm_asked_at'] ?? null;
            $nudged = $contact['reconfirm_nudged_at'] ?? null;
            if ($asked === null || ($nudged === null && $now >= $asked + Schedule::RECONFIRM_NUDGE)) {
                if ($this->sendConfirmation($contact['id'], 'reconfirm')) {
                    $did[] = "Asked {$contact['name']} to reconfirm.";
                }
            }
        }

        // Yearly: prompt the owner to review what each contact holds.
        if ($state['armed'] && $state['review_at'] !== null && $now >= $state['review_at'] + Schedule::YEAR) {
            if (($state['review_asked_at'] ?? 0) < $state['review_at']) {
                if ($this->outbox->send((string) $config['owner_email'], Messages::ownerAnnualReview($this->url($config, 'index.php', ['p' => 'review'])))) {
                    $state['review_asked_at'] = $now;
                    $this->saveState($state);
                    $did[] = 'Sent the yearly review prompt.';
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Things the owner does
    // ------------------------------------------------------------------

    /** @return string[] reasons the switch cannot be armed; empty on success */
    public function arm(): array
    {
        return $this->store->locked(function (): array {
            $blocking = array_values(array_filter($this->checklist(), static fn (array $i): bool => !$i['ok'] && $i['blocking']));
            if ($blocking) {
                return array_column($blocking, 'label');
            }
            $now = Clock::now();
            $config = $this->config();
            $state = $this->state();
            $state['armed'] = true;
            $state['armed_at'] = $now;
            $state['paused_until'] = null;
            $state['cycle'] = null;
            $state['last_checkin_at'] = $now; // arming is proof of life
            $state['last_checkin_via'] = 'arming the switch';
            $state['next_due_at'] = $this->nextSlot($config, $now);
            $state['review_at'] = $now;
            $this->saveState($state);
            $this->store->log('switch.armed', t('The switch was armed.'));
            $this->outbox->send($this->ownerAddresses($config), Messages::ownerArmed($config, $state['next_due_at']));
            return [];
        });
    }

    /** @return string[] names told it was a false alarm */
    public function disarm(string $reason = 'You disarmed it from the dashboard.', ?string $shownReason = null): array
    {
        // $reason is the English wording; the history and the owner's email get it in the current language.
        $shownReason ??= $reason === 'You disarmed it from the dashboard.' ? t('You disarmed it from the dashboard.') : $reason;
        return $this->store->locked(function () use ($reason, $shownReason): array {
            $config = $this->config();
            $state = $this->state();
            if (!$state['armed']) {
                return [];
            }
            $told = $this->closeCycle($state, $config);
            $state['armed'] = false;
            $state['paused_until'] = null;
            $state['next_due_at'] = null;
            $this->saveState($state);
            $this->store->log('switch.disarmed', t('The switch was disarmed. {reason}', ['reason' => $shownReason]));
            $this->outbox->send($this->ownerAddresses($config), Messages::ownerDisarmed($shownReason));
            return $told;
        });
    }

    /** @return array{until: int, told: string[]}|null null if the switch is not armed */
    public function pause(int $days): ?array
    {
        return $this->store->locked(function () use ($days): ?array {
            $config = $this->config();
            $state = $this->state();
            if (!$state['armed']) {
                return null;
            }
            $days = min(Schedule::PAUSE_MAX_DAYS, max(1, $days));
            $now = Clock::now();
            $told = $this->closeCycle($state, $config);
            $state['last_checkin_at'] = $now; // pausing is proof of life
            $state['last_checkin_via'] = 'pausing the switch';
            $state['paused_until'] = $now + $days * Schedule::DAY;
            $this->saveState($state);
            $this->store->log('pause.started', t('The switch was paused for {duration}.', ['duration' => tn('{n} day', '{n} days', $days)]));
            return ['until' => $state['paused_until'], 'told' => $told];
        });
    }

    public function resume(): void
    {
        $this->store->locked(function (): void {
            $state = $this->state();
            if ($state['paused_until'] === null) {
                return;
            }
            $now = Clock::now();
            $state['paused_until'] = null;
            $state['last_checkin_at'] = $now;
            $state['last_checkin_via'] = 'ending the pause';
            $state['next_due_at'] = $this->nextSlot($this->config(), $now);
            $this->saveState($state);
            $this->store->log('pause.ended', t('The pause was ended early.'));
        });
    }

    /**
     * Record a check-in. This is the only thing that resets the timer.
     *
     * @return array{stage: ?string, told: string[], next_due_at: ?int, armed: bool, paused_until: ?int}
     */
    /** How a stored check-in method reads to the owner. The stored values stay English. */
    public static function viaLabel(string $via): string
    {
        if (preg_match('/^another node \((.*)\)$/Ds', $via, $m)) {
            return t('another node ({via})', ['via' => t($m[1])]);
        }
        return t($via);
    }

    public function checkIn(string $via): array
    {
        return $this->store->locked(function () use ($via): array {
            $config = $this->config();
            $state = $this->state();
            $now = Clock::now();
            $stage = $state['cycle']['stage'] ?? null;
            $told = $this->closeCycle($state, $config);
            $state['last_checkin_at'] = $now;
            $state['last_checkin_via'] = $via;
            if ($state['armed']) {
                $state['next_due_at'] = $this->nextSlot($config, max($now, (int) $state['paused_until']));
            }
            $this->saveState($state);
            $this->store->log('checkin', t('Checked in by {via}.', ['via' => self::viaLabel($via)])
                . ($told ? ' ' . t('False-alarm notices sent to {names}.', ['names' => implode(', ', $told)]) : ''));
            return [
                'stage' => $stage,
                'told' => $told,
                'next_due_at' => $state['next_due_at'],
                'armed' => (bool) $state['armed'],
                'paused_until' => $state['paused_until'],
            ];
        });
    }

    /**
     * Record a check-in made on another node, stamped with when it happened there.
     * Returns null when it is older than the check-in already held.
     *
     * @return array{stage: ?string, told: string[], next_due_at: ?int, armed: bool, paused_until: ?int}|null
     */
    public function checkInFromPeer(int $at, string $via): ?array
    {
        return $this->store->locked(function () use ($at, $via): ?array {
            $config = $this->config();
            $state = $this->state();
            if ($state['last_checkin_at'] !== null && $at < (int) $state['last_checkin_at']) {
                return null;
            }
            $stage = $state['cycle']['stage'] ?? null;
            $told = $this->closeCycle($state, $config);
            $state['last_checkin_at'] = $at;
            $state['last_checkin_via'] = $via;
            if ($state['armed']) {
                $state['next_due_at'] = $this->nextSlot($config, max($at, (int) $state['paused_until']));
            }
            $this->saveState($state);
            $this->store->log('checkin', t('Checked in by {via}.', ['via' => self::viaLabel($via)])
                . ($told ? ' ' . t('False-alarm notices sent to {names}.', ['names' => implode(', ', $told)]) : ''));
            return [
                'stage' => $stage,
                'told' => $told,
                'next_due_at' => $state['next_due_at'],
                'armed' => (bool) $state['armed'],
                'paused_until' => $state['paused_until'],
            ];
        });
    }

    /** Is this emailed check-in link still usable? Looking never counts as a check-in. */
    public function checkinTokenIsLive(string $token): bool
    {
        $cycle = $this->state()['cycle'];
        return $cycle !== null && Tokens::matches($token, (array) $cycle['tokens']);
    }

    /** Check in with an emailed link. Returns null if the link is spent or expired. */
    public function checkInByToken(string $token): ?array
    {
        return $this->store->locked(function () use ($token): ?array {
            if (!$this->checkinTokenIsLive($token)) {
                return null;
            }
            // Closing the cycle discards every link issued for it, which makes them single use.
            return $this->checkIn('the emailed link');
        });
    }

    /** The owner has reviewed what each contact holds. */
    public function recordReview(array $contactIds): void
    {
        $this->store->locked(function () use ($contactIds): void {
            $now = Clock::now();
            $all = true;
            foreach ($this->contacts->all() as $contact) {
                if (in_array($contact['id'], $contactIds, true)) {
                    $contact['has_needs_at'] = $now;
                    $this->contacts->save($contact);
                } elseif (($contact['status'] ?? '') === 'confirmed') {
                    $all = false;
                }
            }
            if ($all) {
                $state = $this->state();
                $state['review_at'] = $now;
                $this->saveState($state);
                $this->store->log('review.done', t('Yearly review completed.'));
            }
        });
    }

    public function dismissAfterFireNotice(): void
    {
        $this->store->locked(function (): void {
            $state = $this->state();
            $state['after_fire_at'] = null;
            $this->saveState($state);
        });
    }

    // ------------------------------------------------------------------
    // The next of kin
    // ------------------------------------------------------------------

    public function verifyTokenIsLive(string $token): bool
    {
        $cycle = $this->state()['cycle'];
        return $cycle !== null
            && $cycle['stage'] === 'verify'
            && ($cycle['verify']['response'] ?? null) === null
            && Tokens::matches($token, (array) ($cycle['verify']['tokens'] ?? []));
    }

    /** The trusted person an answer link was sent to, or null when the link is no longer live. */
    public function verifyTokenContact(string $token): ?array
    {
        return $this->verifyAnswerer($token)['contact'] ?? null;
    }

    /**
     * Who an answer link belongs to in this cycle, read from the cycle's own chain (never from the contact list):
     * the contact (null when unknown or removed), their place in the chain, the chain's size, whether anyone else
     * in the chain has been asked, and whether a "wrong" from them fires at once. Null when the link is not live.
     *
     * @return array{contact: ?array, id: ?string, position: ?int, size: int, others_asked: bool, fires: bool}|null
     */
    public function verifyAnswerer(string $token): ?array
    {
        if (!$this->verifyTokenIsLive($token)) {
            return null;
        }
        return $this->answerer((array) $this->state()['cycle']['verify'], $token);
    }

    private function answerer(array $verify, string $token): array
    {
        if (!isset($verify['chain'])) {
            // A cycle begun before the chain existed: its only links went to the next of kin.
            $nok = $this->contacts->nok();
            return ['contact' => $nok, 'id' => $nok === null ? null : (string) $nok['id'], 'position' => 1, 'size' => 1,
                'others_asked' => false, 'fires' => $nok !== null];
        }
        $id = null;
        foreach ((array) ($verify['tokens'] ?? []) as $record) {
            if (is_array($record) && is_string($record['contact'] ?? null) && Tokens::matches($token, [$record])) {
                $id = $record['contact'];
            }
        }
        $chain = array_values(array_map('strval', (array) $verify['chain']));
        $at = $id === null ? false : array_search($id, $chain, true);
        $contact = $id === null ? null : $this->contacts->find($id);
        $others = array_diff(array_map('strval', array_keys((array) ($verify['asks'] ?? []))), [(string) $id]);
        return [
            'contact' => $contact,
            'id' => $id,
            'position' => $at === false ? null : $at + 1,
            'size' => count($chain),
            'others_asked' => $others !== [],
            // Only the next of kin, asked first in this very cycle, may fire the switch at once.
            'fires' => $contact !== null && ($contact['kind'] ?? '') === 'nok' && $at === 0,
        ];
    }

    /** How many people are being asked in this cycle (1 for a cycle begun before the chain existed). */
    public function chainSize(): int
    {
        return max(1, count((array) ($this->state()['cycle']['verify']['chain'] ?? [])));
    }

    /**
     * Record a trusted person's answer.
     *
     * "Fine" from anyone ends the cycle. "Wrong" fires at once only when it comes from the next of kin, asked first
     * in this cycle. From anyone else it is noted, nothing is sent early, the others are still asked in turn, and
     * the same link still takes a later "fine".
     *
     * @param string $answer 'fine' or 'wrong'
     * @return string|null 'fine', 'wrong', or null if the link is no longer active
     */
    public function nokAnswer(string $token, string $answer): ?string
    {
        if ($answer !== 'fine' && $answer !== 'wrong') {
            return null;
        }
        return $this->store->locked(function () use ($token, $answer): ?string {
            if (!$this->verifyTokenIsLive($token)) {
                return null;
            }
            $config = $this->config();
            $state = $this->state();
            $now = Clock::now();
            $role = $this->answerer((array) $state['cycle']['verify'], $token);
            $who = $role['contact'];

            if ($answer === 'wrong') {
                if ($role['fires']) {
                    $state['cycle']['verify']['response'] = $answer;
                    $state['cycle']['verify']['responded_at'] = $now;
                    $state['cycle']['verify']['tokens'] = [];
                    $did = [];
                    $this->fire($state, $config, 'nok_wrong', $did);
                    return 'wrong';
                }
                // Anyone else is a fallback, not an extra vote to fire. Their link stays live for a later "fine".
                $state['cycle']['verify']['answers'][$role['id'] ?? '?'] = ['answer' => 'wrong', 'at' => $now];
                $this->saveState($state);
                $this->store->log('cycle.trusted_wrong', t('{name} answered "Something\'s wrong". Nothing is sent early; the others are still asked in turn.', ['name' => $who['name'] ?? '?']));
                return 'wrong';
            }

            // "All fine": this cycle ends and nobody else is asked.
            $whoId = (string) ($role['id'] ?? '');
            $others = array_diff($this->askedIds($state['cycle']), [$whoId]);
            $key = (int) ($state['cycle']['due_at'] ?? 0);
            $state['cycle']['verify']['response'] = $answer;
            $state['cycle']['verify']['responded_at'] = $now;
            $state['cycle']['verify']['tokens'] = [];
            $this->rememberVouched($state, $now, $whoId === '' ? null : $whoId);
            $state['cycle'] = null;
            $state['next_due_at'] = $this->nextSlot($config, $now);
            foreach ($others as $other) {
                $state['trusted_clear_queue'][(string) $other] = (string) ($who['name'] ?? '');
            }
            $this->saveState($state);
            // The cycle key travels with the answer, so a peer applies it to this cycle only.
            $this->store->log('cycle.vouched', $role['fires']
                ? t('The next of kin answered "All fine". Nobody else was contacted.')
                : t('{name} answered "All fine". Nobody else will be asked.', ['name' => $who['name'] ?? '?']),
                ['key' => $key] + ($whoId === '' ? [] : ['by' => $whoId]));
            $this->outbox->send($this->ownerAddresses($config), Messages::ownerVouched($config, $who ?? $this->contacts->nok(), $state['next_due_at'], $others !== []));
            $this->sendAllClears($state, $config);
            return 'fine';
        });
    }

    /** @return string[] ids of the people this node asked and reached in a cycle (people a peer node asked are not included) */
    private function askedIds(array $cycle): array
    {
        $verify = (array) ($cycle['verify'] ?? []);
        if (!isset($verify['asks'])) {
            // A cycle begun before the chain existed: the next of kin is the only person asked.
            $nok = !empty($verify['reached']) ? $this->contacts->nok() : null;
            return $nok === null ? [] : [(string) $nok['id']];
        }
        $out = [];
        foreach ((array) $verify['asks'] as $id => $ask) {
            if (is_array($ask) && !empty($ask['reached']) && ($ask['own'] ?? true)) {
                $out[] = (string) $id;
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Contacts: confirming, declining, reconfirming
    // ------------------------------------------------------------------

    /**
     * Email a contact the link that lets them confirm.
     *
     * @param string|null $purpose 'confirm', 'email_change' or 'reconfirm'; worked out if null
     */
    public function sendConfirmation(string $id, ?string $purpose = null): bool
    {
        return $this->store->locked(function () use ($id, $purpose): bool {
            $contact = $this->contacts->find($id);
            if ($contact === null) {
                return false;
            }
            $config = $this->config();
            $now = Clock::now();
            $purpose ??= !empty($contact['pending_email']) ? 'email_change' : 'confirm';
            $address = $purpose === 'email_change' ? (string) $contact['pending_email'] : (string) $contact['email'];

            [$token, $record] = Tokens::issue(Schedule::CONFIRM_TOKEN_TTL);
            $email = Messages::contactConfirm($config, $contact, $this->url($config, 'confirm.php', ['t' => $token]), $purpose);
            if (!$this->outbox->send($address, $email, 'contact')) {
                return false;
            }
            $contact['token'] = $record + ['purpose' => $purpose];
            $contact['confirm_sent_at'] = $now;
            if ($purpose === 'reconfirm') {
                if (($contact['reconfirm_asked_at'] ?? null) === null) {
                    $contact['reconfirm_asked_at'] = $now;
                } else {
                    $contact['reconfirm_nudged_at'] = $now;
                }
            } elseif ($purpose === 'confirm' && $contact['status'] === 'declined') {
                $contact['status'] = 'unconfirmed';
            }
            $this->contacts->save($contact);
            return true;
        });
    }

    /**
     * A contact pressed "Yes, I confirm" or "No, remove me".
     *
     * @return array{contact: array, accepted: bool}|null null if the link is no longer active
     */
    public function contactAnswer(string $token, bool $accept): ?array
    {
        return $this->store->locked(function () use ($token, $accept): ?array {
            $contact = $this->contacts->forToken($token);
            if ($contact === null) {
                return null;
            }
            $config = $this->config();
            $now = Clock::now();
            $purpose = (string) ($contact['token']['purpose'] ?? 'confirm');
            $contact['token'] = null;
            $extra = '';

            if ($accept) {
                if ($purpose === 'email_change' && !empty($contact['pending_email'])) {
                    $contact['email'] = $contact['pending_email'];
                }
                $contact['pending_email'] = null;
                $contact['status'] = 'confirmed';
                $contact['confirmed_at'] ??= $now;
                $contact['reconfirmed_at'] = $now;
                $contact['reconfirm_asked_at'] = null;
                $contact['reconfirm_nudged_at'] = null;
                $this->contacts->save($contact);
                $this->store->log('contact.confirmed', t('{name} confirmed as {role}.', ['name' => $contact['name'], 'role' => Contacts::label((string) $contact['role_label'])]));
            } elseif ($purpose === 'email_change') {
                // The new address said no. The confirmed address stays in use.
                $contact['pending_email'] = null;
                $this->contacts->save($contact);
                $this->store->log('contact.declined', t('The new address for {name} was declined. The old one is still in use.', ['name' => $contact['name']]));
                $extra = t('Their previous address is still in use.');
            } else {
                $contact['status'] = 'declined';
                $contact['pending_email'] = null;
                $this->contacts->save($contact);
                $this->store->log('contact.declined', t('{name} declined to be {role}.', ['name' => $contact['name'], 'role' => Contacts::label((string) $contact['role_label'])]));

                // An armed switch must always have its required contacts.
                $state = $this->state();
                if ($state['armed'] && !$this->requiredContactsConfirmed()) {
                    $this->disarm(
                        "{$contact['name']} declined to be your " . Contacts::role($contact) . ', so a required contact is missing.',
                        t('{name} declined to be your {role}, so a required contact is missing.', ['name' => $contact['name'], 'role' => Contacts::role($contact)])
                    );
                    $extra = t('Your switch has been disarmed, because it needs this role filled.');
                }
            }

            $this->outbox->send($this->ownerAddresses($config), Messages::ownerContactAnswered($contact, $accept, $extra));
            return ['contact' => $contact, 'accepted' => $accept];
        });
    }

    public function requiredContactsConfirmed(): bool
    {
        // Only the next of kin is required. A technical helper is recommended, never blocking.
        return $this->confirmedNok() !== null;
    }

    // ------------------------------------------------------------------
    // Status, for the dashboard, the weekly email and the health check
    // ------------------------------------------------------------------

    /**
     * What has to be true before the switch can be armed.
     *
     * @return array<int, array{key: string, label: string, ok: bool, blocking: bool, hint: string}>
     */
    public function checklist(): array
    {
        $state = $this->state();
        $config = $this->config();
        $now = Clock::now();
        $items = [];

        $ticked = $state['last_tick_at'] !== null && $now - $state['last_tick_at'] <= Schedule::CRON_STALE;
        $items[] = [
            'key' => 'scheduler',
            'label' => t('The scheduler is running'),
            'ok' => $ticked,
            'blocking' => true,
            'hint' => $state['last_tick_at'] === null
                ? t('No run has been recorded yet. Add the crontab line shown in Settings.')
                : t('The last run was {when}. Check the crontab line shown in Settings.', ['when' => relative($state['last_tick_at'])]),
        ];

        $probe = (string) ($state['probe']['result'] ?? 'unknown');
        $probeOk = in_array($probe, ['protected', 'outside'], true) || ($probe === 'unknown' && !empty($state['probe']['browser_ok_at']));
        $items[] = [
            'key' => 'data',
            'label' => t('The data folder cannot be read from the web'),
            'ok' => $probeOk,
            'blocking' => true,
            'hint' => $probe === 'exposed'
                ? t('Files in the data folder can be opened in a browser. See "Protecting the data folder" in the README.')
                : t('The server could not test this by itself. Run the check from Settings.'),
        ];

        $nok = $this->contacts->nok();
        $items[] = [
            'key' => 'nok',
            'label' => t('Your next of kin has confirmed'),
            'ok' => $nok !== null && $nok['status'] === 'confirmed',
            'blocking' => true,
            'hint' => $nok === null ? t('Add your next of kin.') : self::contactHint($nok),
        ];

        $technical = array_values(array_filter($this->contacts->all(), static fn (array $c): bool => $c['kind'] === 'technical'));
        $items[] = [
            'key' => 'technical',
            'label' => t('Technical helper (optional)'),
            'ok' => $this->contacts->confirmedOfKind('technical') !== [],
            'blocking' => false,
            'hint' => $technical === [] ? t('Someone who can keep your servers, accounts or projects running.') : self::contactHint($technical[0]),
        ];

        $unwritten = [];
        $unticked = [];
        foreach ($this->contacts->confirmed() as $contact) {
            $text = ($contact['message']['subject'] ?? '') . ' ' . ($contact['message']['body'] ?? '')
                . ' ' . ($contact['verify_message']['body'] ?? '');
            if (Messages::hasMarker($text)) {
                $unwritten[] = $contact['name'];
            }
            if (empty($contact['has_needs_at'])) {
                $unticked[] = $contact['name'];
            }
        }
        $any = $this->contacts->confirmed() !== [];
        $items[] = [
            'key' => 'messages',
            'label' => t('Every message says where things are'),
            'ok' => $any && $unwritten === [],
            'blocking' => true,
            'hint' => $any
                ? t('Still holding starter text in square brackets: {names}.', ['names' => implode(', ', $unwritten)])
                : t('Checked once your contacts have confirmed.'),
        ];
        $items[] = [
            'key' => 'needs',
            'label' => t('Each contact has been given what they need'),
            'ok' => $any && $unticked === [],
            'blocking' => true,
            'hint' => $any
                ? t('Not ticked yet: {names}.', ['names' => implode(', ', $unticked)])
                : t('Checked once your contacts have confirmed.'),
        ];

        $items[] = [
            'key' => 'backup',
            'label' => t('A second email address for reminders'),
            'ok' => !empty($config['backup_email']),
            'blocking' => false,
            'hint' => t('Recommended: an address with a different provider, in case your main one fails. Add it in Settings.'),
        ];

        return $items;
    }

    private static function contactHint(array $contact): string
    {
        return match ($contact['status']) {
            'declined' => t('{name} declined. Choose someone else.', ['name' => $contact['name']]),
            'confirmed' => '',
            default => empty($contact['confirm_sent_at'])
                ? t('Send {name} their confirmation email.', ['name' => $contact['name']])
                : t('Waiting for {name} to confirm. The email went {when}.', ['name' => $contact['name'], 'when' => relative($contact['confirm_sent_at'])]),
        };
    }

    /**
     * Problems worth the owner's attention right now.
     *
     * @return array<int, array{key: string, level: string, text: string}> level is 'bad' or 'warn'
     */
    public function warnings(): array
    {
        $state = $this->state();
        $config = $this->config();
        $mail = $this->store->read('mail');
        $now = Clock::now();
        $out = [];

        foreach ($this->store->recoveredFiles() as $name) {
            $out[] = ['key' => 'store', 'level' => 'bad', 'text' => t('{name}.json was damaged and the last good copy is in use. Check what changed recently, then save any setting to rewrite the file.', ['name' => $name])];
        }

        if ($state['last_tick_at'] === null) {
            $out[] = ['key' => 'scheduler', 'level' => 'bad', 'text' => t('The scheduler has never run. Nothing will be sent until it does.')];
        } elseif ($now - $state['last_tick_at'] > Schedule::CRON_STALE) {
            $out[] = ['key' => 'scheduler', 'level' => 'bad', 'text' => t('The scheduler last ran {when}. While it is stopped, the switch cannot send anything.', ['when' => relative($state['last_tick_at'])])];
        }

        if ($this->federated() && $this->coordinator->isolated($now)) {
            $heard = 0;
            foreach ((array) $this->store->read('peers') as $peer) {
                $heard = max($heard, (int) (is_array($peer) ? ($peer['seen_at'] ?? 0) : 0));
            }
            $out[] = ['key' => 'peers', 'level' => 'bad', 'text' => $heard > 0
                ? t('This node has not heard from any other node for {duration}. Serious steps are delayed while it is cut off.', ['duration' => duration($now - $heard)])
                : t('This node has not heard from any other node yet. Serious steps are delayed while it is cut off.')];
        }

        if (!empty($mail['last_send_error'])) {
            $error = $mail['last_send_error'];
            $out[] = ['key' => 'mail', 'level' => 'bad', 'text' => t('The last email could not be sent ({when}): {error}', ['when' => relative((int) $error['at']), 'error' => (string) $error['error']])];
        }

        // Label and error are stored data; they stay plain text and the pages escape them with e().
        foreach ((array) ($mail['channels'] ?? []) as $channel) {
            if (!is_array($channel) || empty($channel['error_at']) || (int) $channel['error_at'] <= (int) ($channel['ok_at'] ?? 0)) {
                continue;
            }
            $out[] = ['key' => 'channels', 'level' => 'bad', 'text' => t('The {label} channel failed {when}: {error}', [
                'label' => (string) ($channel['label'] ?? t('notification')),
                'when' => relative((int) $channel['error_at']),
                'error' => (string) ($channel['error'] ?? t('unknown error')),
            ])];
        }

        $probe = (string) ($state['probe']['result'] ?? '');
        if ($probe === 'exposed') {
            $out[] = ['key' => 'data', 'level' => 'bad', 'text' => t('Files in the data folder can be opened in a browser.')];
        } elseif ($probe === 'unknown' && empty($state['probe']['browser_ok_at'])) {
            $out[] = ['key' => 'data', 'level' => 'warn', 'text' => t('The server could not check whether the data folder is blocked from the web.')];
        }

        if ($state['armed'] && !$this->requiredContactsConfirmed()) {
            $out[] = ['key' => 'required', 'level' => 'bad', 'text' => t('A required contact is missing or unconfirmed.')];
        }

        foreach ($this->contacts->all() as $contact) {
            $name = $contact['name'] ?: $contact['email'];
            if ($contact['status'] === 'declined') {
                $out[] = ['key' => 'contact', 'level' => 'warn', 'text' => t('{name} declined and will not be contacted.', ['name' => $name])];
            } elseif ($contact['status'] === 'unconfirmed') {
                $out[] = ['key' => 'contact', 'level' => 'warn', 'text' => t('{name} has not confirmed, so will not be contacted.', ['name' => $name])];
            } else {
                if (!empty($contact['pending_email'])) {
                    $out[] = ['key' => 'contact', 'level' => 'warn', 'text' => t('{name} has not confirmed their new address. The old one is still in use.', ['name' => $name])];
                }
                $asked = $contact['reconfirm_asked_at'] ?? null;
                if ($asked !== null && $now - $asked > Schedule::RECONFIRM_NUDGE) {
                    $out[] = ['key' => 'contact', 'level' => 'warn', 'text' => t('{name} has not answered their yearly reconfirmation. Check their address still works.', ['name' => $name])];
                }
                if ($state['armed'] && Messages::hasMarker(($contact['message']['body'] ?? '') . ($contact['verify_message']['body'] ?? ''))) {
                    $out[] = ['key' => 'contact', 'level' => 'bad', 'text' => t('The message for {name} still holds starter text in square brackets.', ['name' => $name])];
                }
            }
        }

        if ($state['armed'] && $state['review_at'] !== null && $now >= $state['review_at'] + Schedule::YEAR) {
            $out[] = ['key' => 'review', 'level' => 'warn', 'text' => t('Your yearly review is due: confirm each contact still has what they need.')];
        }

        if (!empty($config['base_url']) && !str_starts_with((string) $config['base_url'], 'https://')) {
            $out[] = ['key' => 'https', 'level' => 'warn', 'text' => t('This install is not using HTTPS. Links in its emails can be read in transit.')];
        }

        return $out;
    }

    /**
     * The timetable for a cycle: when each step happened, or when it will.
     * Used both to decide what is due and to draw the track on the dashboard.
     *
     * Each trusted person after the first has an "ask:<id>" step. Before the first is asked, $trust
     * (chain, mode, gap, window) projects those steps for the dashboard.
     *
     * @return array<string, array{at: int, done: bool}>
     */
    public static function plan(?array $cycle, ?int $nextDue, ?array $trust = null): array
    {
        $start = $cycle['started_at'] ?? $nextDue;
        if ($start === null) {
            return [];
        }
        $gap = Schedule::MIN_STEP_GAP;
        $reminders = (array) ($cycle['reminders'] ?? []);
        $plan = ['email' => ['at' => (int) $start, 'done' => $cycle !== null]];

        $previous = null;
        foreach (Schedule::OWNER_REMINDERS as $i => $offset) {
            $at = $reminders[$i] ?? max($start + $offset, $previous === null ? 0 : $previous + $gap);
            $plan['reminder' . ($i + 1)] = ['at' => (int) $at, 'done' => isset($reminders[$i])];
            $previous = $at;
        }

        $verify = $cycle['verify'] ?? null;
        $asked = $verify['asked_at'] ?? max($start + Schedule::OWNER_WINDOW, $previous + $gap);
        $plan['verify'] = ['at' => (int) $asked, 'done' => isset($verify['asked_at'])];

        // The trusted people after the first: one interval after the one before, or all together after one interval.
        $setup = is_array($verify) && isset($verify['chain']) ? $verify : ($trust ?? []);
        $ids = array_values(array_map('strval', (array) ($setup['chain'] ?? [])));
        $lastAsk = (int) $asked;
        if (count($ids) > 1) {
            $step = max(60, (int) ($setup['gap'] ?? 0));
            $together = ($setup['mode'] ?? '') === 'all_at_once';
            $before = (int) $asked;
            foreach (array_slice($ids, 1) as $id) {
                $did = $verify['asks'][$id]['asked_at'] ?? null;
                $at = (int) ($did ?? ($together ? $asked + $step : $before + $step));
                $plan['ask:' . $id] = ['at' => $at, 'done' => $did !== null, 'id' => $id];
                $before = $at;
                $lastAsk = max($lastAsk, $at);
            }
        }
        $window = (int) ($setup['window'] ?? 0);

        $reminded = $verify['reminded_at'] ?? $asked + Schedule::NOK_REMINDER;
        $plan['nok_reminder'] = ['at' => (int) $reminded, 'done' => isset($verify['reminded_at'])];

        $fired = $cycle['fired_at'] ?? max($asked + Schedule::NOK_WINDOW, $reminded + $gap, $window > 0 ? $lastAsk + $window : 0);
        $plan['fire'] = ['at' => (int) $fired, 'done' => isset($cycle['fired_at'])];

        return $plan;
    }

    /**
     * Everything the dashboard needs to describe the switch.
     *
     * mode: setup | armed | paused | overdue | verify | fired
     */
    public function status(): array
    {
        $state = $this->state();
        $cycle = $state['cycle'];

        if (!$state['armed']) {
            $mode = 'setup';
        } elseif ($state['paused_until'] !== null) {
            $mode = 'paused';
        } elseif ($cycle === null) {
            $mode = 'armed';
        } else {
            $mode = ['checkin' => 'overdue', 'verify' => 'verify', 'fired' => 'fired'][$cycle['stage']] ?? 'armed';
        }

        return [
            'mode' => $mode,
            'state' => $state,
            'plan' => $state['armed'] && $state['paused_until'] === null
                ? self::plan($cycle, $state['next_due_at'], $this->trustDescriptor($this->config()))
                : [],
        ];
    }

    /** True once the cycle has fired and every confirmed contact has their message. */
    public function released(): bool
    {
        $cycle = $this->state()['cycle'];
        return is_array($cycle) && ($cycle['stage'] ?? '') === 'fired';
    }

    /**
     * For external monitors: is anything broken that the owner would not
     * otherwise hear about? Deliberately says nothing private.
     *
     * @return array{ok: bool, armed: bool, problems: string[]}
     */
    public function health(): array
    {
        $problems = [];
        foreach ($this->warnings() as $warning) {
            if ($warning['level'] === 'bad') {
                $problems[$warning['key']] = true;
            }
        }
        $state = $this->state();
        return [
            'ok' => $problems === [],
            'armed' => (bool) $state['armed'],
            'problems' => array_keys($problems),
        ];
    }

    /**
     * The emails a contact would receive, filled in as of now.
     *
     * @return array<string, Email>
     */
    public function preview(array $contact): array
    {
        $config = $this->config();
        $state = $this->state();
        $vars = Messages::vars($config, $state, $this->contacts->all(), $contact);
        $out = [];
        if ($this->contacts->trustedNumber((string) ($contact['id'] ?? '')) !== null) {
            $out[t('When you miss a check-in and its reminders')] = Messages::nokVerify(
                $config,
                $contact,
                $vars,
                $this->url($config, 'respond.php', ['t' => 'EXAMPLE', 'a' => 'fine']),
                $this->url($config, 'respond.php', ['t' => 'EXAMPLE', 'a' => 'wrong']),
                Clock::now() + Schedule::NOK_WINDOW,
                false
            );
        }
        $out[t('When the switch fires')] = Messages::fired($config, $contact, $vars);
        return $out;
    }

    // ------------------------------------------------------------------
    // Federation
    // ------------------------------------------------------------------

    /** Seconds two nodes' clocks may differ before a check-in is read as older than the cycle. */
    private const CLOCK_SKEW = Coordinator::CLOCK_SKEW;

    private function federated(): bool
    {
        return $this->coordinator !== null && Cluster::enabled($this->store);
    }

    /** The time a peer did $step in cycle $key, not before $notBefore, or null (always null without federation). */
    private function peerDid(int $key, string $step, ?int $notBefore = null): ?int
    {
        return $this->federated() ? $this->coordinator->peerDid($key, $step, $notBefore) : null;
    }

    /**
     * The time to adopt a peer's firing at, or null. Only while this cycle waits in verify and
     * this node would fire now anyway: a peer can never make this node contact anyone early.
     */
    private function peerFire(array $cycle, int $now): ?int
    {
        if (!$this->federated() || ($cycle['stage'] ?? null) !== 'verify') {
            return null;
        }
        $plan = self::plan($cycle, null);
        // Never before the last trusted person has been asked on this node's own timeline.
        if (self::pendingAsks($plan) !== []) {
            return null;
        }
        $due = $plan['fire']['at'] + $this->extraDelay('fire', $now);
        if ($now < $due - self::CLOCK_SKEW) {
            return null;
        }
        $t = $this->coordinator->peerDid((int) ($cycle['due_at'] ?? 0), 'fire', $due - self::CLOCK_SKEW);
        return $t === null ? null : min($t, $now);
    }

    /**
     * The earliest believable time of a peer's delivery in this fired cycle: the local fire due time,
     * or the kin's "something's wrong" answer on a peer when that came after the local question.
     */
    private function deliverFloor(array $cycle): int
    {
        $plan = self::plan(['fired_at' => null] + $cycle, null);
        $floor = min($plan['fire']['at'], (int) ($cycle['fired_at'] ?? PHP_INT_MAX));
        $nok = $this->coordinator->peerCycle((int) ($cycle['due_at'] ?? 0))['nok'] ?? null;
        if (is_array($nok) && ($nok['answer'] ?? null) === 'wrong' && is_int($nok['at'] ?? null)
            && $nok['at'] >= $plan['verify']['at'] - self::CLOCK_SKEW) {
            $floor = min($floor, $nok['at']);
        }
        return $floor - self::CLOCK_SKEW;
    }

    /** Mark the contacts a peer believably delivered to, so this node does not send them a second message. */
    private function adoptPeerDeliveries(array &$state): void
    {
        $key = (int) ($state['cycle']['due_at'] ?? 0);
        $floor = $this->deliverFloor($state['cycle']);
        foreach ($this->contacts->confirmed() as $contact) {
            $id = (string) $contact['id'];
            if (isset($state['cycle']['notified'][$id])) {
                continue;
            }
            $t = $this->coordinator->peerDid($key, 'deliver:' . $id, $floor);
            if ($t === null) {
                continue;
            }
            $state['cycle']['notified'][$id] = $t;
            $state['cycle']['steps']['deliver:' . $id] = $t;
            if (!in_array($id, (array) ($state['cycle']['peer_told'] ?? []), true)) {
                $state['cycle']['peer_told'][] = $id;
            }
        }
    }

    /** May this node send $step now? Always yes without federation. */
    private function may(int $key, string $step, int $due, int $now): bool
    {
        return !$this->federated() || $this->coordinator->mayAct($key, $step, $due, $now);
    }

    private function extraDelay(string $step, int $now): int
    {
        return $this->federated() ? $this->coordinator->extraDelay($step, $now) : 0;
    }

    /** Start the local cycle a peer already started, without emailing the owner. */
    private function adoptCycle(array &$state, int $at, array &$did): void
    {
        $state['cycle'] = [
            'id' => gmdate('Ymd-His', $at),
            'due_at' => $state['next_due_at'],
            'started_at' => $at,
            'stage' => 'checkin',
            'tokens' => [],
            'reminders' => [],
            'verify' => null,
            'fired_at' => null,
            'fired_reason' => null,
            'notified' => [],
            'owner_told_fired_at' => null,
            'steps' => ['checkin' => $at],
        ];
        $this->saveState($state);
        $this->store->log('cycle.started', t('The weekly check-in email was sent by another node.'));
        $did[] = 'Joined the cycle another node started.';
    }

    private function adoptFire(array &$state, int $at, string $reason, array &$did): void
    {
        $state['cycle']['stage'] = 'fired';
        $state['cycle']['fired_at'] = $at;
        $state['cycle']['fired_reason'] = $reason;
        $state['cycle']['steps']['fire'] = $at;
        $this->saveState($state);
        $this->store->log('cycle.fired', t('Another node fired the switch.'));
        $did[] = 'The switch fired (another node).';
    }

    /** @return array<string,int> the steps a cycle has taken, by name */
    private function cycleSteps(array $c): array
    {
        $steps = [];
        if (isset($c['started_at'])) {
            $steps['checkin'] = (int) $c['started_at'];
        }
        foreach ((array) ($c['reminders'] ?? []) as $i => $t) {
            $steps['reminder' . ($i + 1)] = (int) $t;
        }
        if (isset($c['verify']['asked_at'])) {
            $steps['verify'] = (int) $c['verify']['asked_at'];
        }
        if (isset($c['verify']['reminded_at'])) {
            $steps['nok_reminder'] = (int) $c['verify']['reminded_at'];
        }
        foreach ((array) ($c['verify']['asks'] ?? []) as $id => $ask) {
            if (is_array($ask) && isset($ask['asked_at']) && ($c['verify']['chain'][0] ?? null) !== $id) {
                $steps['ask:' . $id] = (int) $ask['asked_at'];
            }
        }
        if (isset($c['fired_at'])) {
            $steps['fire'] = (int) $c['fired_at'];
        }
        foreach ((array) ($c['notified'] ?? []) as $id => $t) {
            $steps['deliver:' . $id] = (int) $t;
        }
        return array_map('intval', (array) ($c['steps'] ?? []) + $steps);
    }

    /** @param string|null $by the id of the trusted person who answered, when known */
    private function rememberVouched(array &$state, int $at, ?string $by = null): void
    {
        if (!is_array($state['cycle'])) {
            return;
        }
        $state['last_vouched'] = [
            'key' => (int) ($state['cycle']['due_at'] ?? 0),
            'at' => $at,
            'steps' => $this->cycleSteps($state['cycle']),
        ] + ($by === null ? [] : ['by' => $by]);
    }

    /** This node's state as peers see it. */
    public function nodeState(string $nodeId): array
    {
        $state = $this->state();
        $cycle = null;
        if (is_array($state['cycle'])) {
            $c = $state['cycle'];
            $steps = $this->cycleSteps($c);
            $answer = $c['verify']['response'] ?? null;
            $cycle = [
                'key' => (int) ($c['due_at'] ?? 0),
                'stage' => (string) $c['stage'],
                'steps' => $steps,
                'fired_at' => $c['fired_at'] ?? null,
                'fired_reason' => $c['fired_reason'] ?? null,
                'nok' => $answer === null ? null : ['answer' => $answer, 'at' => (int) ($c['verify']['responded_at'] ?? 0)],
            ];
            // The people this cycle asks and its timing, so peers can wait for the longest chain.
            if (isset($c['verify']['chain'])) {
                $cycle['trust'] = self::trustOf((array) $c['verify']);
            }
        }
        return [
            'node' => $nodeId,
            'at' => Clock::now(),
            'last_checkin_at' => $state['last_checkin_at'],
            'last_checkin_via' => $state['last_checkin_via'],
            'armed' => (bool) $state['armed'],
            'released' => $this->released(),
            'cycle' => $cycle,
            // "All fine" ends a cycle, so a peer that missed it would fire. The answer stays visible until replaced.
            'vouched' => is_array($state['last_vouched'] ?? null)
                ? ['key' => (int) $state['last_vouched']['key'], 'at' => (int) $state['last_vouched']['at'],
                    'steps' => array_map('intval', (array) $state['last_vouched']['steps'])]
                    + (is_string($state['last_vouched']['by'] ?? null) ? ['by' => $state['last_vouched']['by']] : [])
                : null,
        ];
    }

    /**
     * Take in what peers have done: a check-in, a firing, a next-of-kin answer.
     * Safe to call repeatedly; a second call changes nothing.
     *
     * @return string[] what changed, for logs
     */
    public function applyRemote(): array
    {
        if (!$this->federated()) {
            return [];
        }
        return $this->store->locked(function (): array {
            $config = $this->config();
            $state = $this->state();
            $did = [];
            if (empty($config['installed_at']) || !$state['armed']) {
                return [];
            }

            $latest = $this->coordinator->latestPeerCheckin();
            if (
                $latest !== null
                && ($state['last_checkin_at'] === null || $latest['at'] > $state['last_checkin_at'])
                && ($state['cycle'] === null || $latest['at'] >= (int) $state['cycle']['started_at'] - self::CLOCK_SKEW)
            ) {
                $told = $this->closeCycle($state, $config);
                $state['last_checkin_at'] = $latest['at'];
                $state['last_checkin_via'] = 'another node (' . $latest['via'] . ')';
                $state['next_due_at'] = $this->nextSlot($config, max($latest['at'], (int) $state['paused_until']));
                $this->saveState($state);
                $this->store->log('checkin', t('Checked in by another node ({via}).', ['via' => self::viaLabel((string) $latest['via'])])
                    . ($told ? ' ' . t('False-alarm notices sent to {names}.', ['names' => implode(', ', $told)]) : ''));
                $did[] = 'A check-in on another node ended the cycle.';
            }

            if ($state['cycle'] !== null && $state['paused_until'] === null) {
                $key = (int) ($state['cycle']['due_at'] ?? 0);
                $pc = $this->coordinator->peerCycle($key);

                // Only "all fine" is taken from a peer: it can stop this node, never make it fire. It comes first,
                // so a peer's firing heard in the same sync never wins over a "fine" given elsewhere.
                foreach ($this->coordinator->peerFines($key) as $fine) {
                    if ($this->applyPeerNok($state, $config, 'fine', $fine['at'], 'another node', $key, $fine['by'])) {
                        $did[] = 'The next of kin answered on another node: fine.';
                        break;
                    }
                }

                // The longest chain and timing any node runs this cycle on.
                if ($state['cycle'] !== null) {
                    $this->mergePeerTrust($state);
                }

                // A peer's firing is only taken in when this node would fire now anyway; then it just avoids duplicates.
                $firedAt = $state['cycle'] === null ? null : $this->peerFire($state['cycle'], Clock::now());
                if ($pc !== null && $firedAt !== null) {
                    $this->adoptFire($state, $firedAt, (string) ($pc['fired_reason'] ?? 'timeout'), $did);
                    $this->adoptPeerDeliveries($state);
                    $this->saveState($state);
                }
            }
            return $did;
        });
    }

    /**
     * A trusted person's answer reported by a peer. Only "all fine" counts, only while the cycle waits for one,
     * and only for the cycle it was given in: $key is that cycle's key (null from a peer too old to send one).
     */
    public function recordNokAnswer(string $answer, int $at, string $via, ?int $key = null, ?string $by = null): void
    {
        if ($answer !== 'fine' && $answer !== 'wrong') {
            return;
        }
        $this->store->locked(function () use ($answer, $at, $via, $key, $by): void {
            $config = $this->config();
            $state = $this->state();
            $this->applyPeerNok($state, $config, $answer, $at, $via, $key, $by);
        });
    }

    /** @param int|null $key the cycle the answer was given in; null when the peer did not say */
    private function applyPeerNok(array &$state, array $config, string $answer, int $at, string $via, ?int $key = null, ?string $by = null): bool
    {
        $cycle = $state['cycle'];
        // A peer's "something's wrong" never fires this node early: the node where the kin answered
        // fires and delivers itself, and this node keeps its own timeline.
        if ($answer !== 'fine' || $cycle === null || ($cycle['verify']['response'] ?? null) !== null) {
            return false;
        }
        $cycleKey = (int) ($cycle['due_at'] ?? 0);
        if ($key !== null && $key !== $cycleKey) {
            return false; // an answer from another cycle
        }
        // An answer matched to this very cycle by its key also ends it while this node still lags before verify:
        // the kin was asked by a peer, so "all fine" must not wait for this node to catch up.
        $lagging = $key !== null && $cycle['stage'] === 'checkin';
        if ($cycle['stage'] !== 'verify' && !$lagging) {
            return false;
        }
        // Never an answer older than this cycle's first question.
        $firstAsk = isset($cycle['verify']['asked_at'])
            ? (int) $cycle['verify']['asked_at']
            : min(self::plan($cycle, null)['verify']['at'], $this->peerDid($cycleKey, 'verify') ?? PHP_INT_MAX);
        if ($at < $firstAsk - self::CLOCK_SKEW) {
            return false;
        }
        // The people this node asked are told it was a false alarm, as when the answer is given here.
        // Each person is asked by one node only, so nobody is told twice.
        $byName = '';
        if ($by !== null) {
            $byName = (string) ($this->contacts->find($by)['name'] ?? '');
        }
        // (A cycle begun before the chain existed asked the next of kin alone, who is the one who answered.)
        $others = isset($cycle['verify']['chain']) ? array_diff($this->askedIds($cycle), $by === null ? [] : [$by]) : [];
        $state['cycle']['verify'] ??= ['asked_at' => $at, 'reached' => false, 'reminded_at' => null, 'tokens' => [], 'response' => null, 'responded_at' => null];
        $state['cycle']['verify']['response'] = $answer;
        $state['cycle']['verify']['responded_at'] = $at;
        $state['cycle']['verify']['tokens'] = [];
        $this->rememberVouched($state, $at, $by);
        $state['cycle'] = null;
        $state['next_due_at'] = $this->nextSlot($config, $at);
        foreach ($others as $other) {
            $state['trusted_clear_queue'][(string) $other] = $byName;
        }
        $this->saveState($state);
        $this->store->log('cycle.vouched', t('The next of kin answered "All fine" ({via}). Nobody else was contacted.', ['via' => self::viaLabel($via)]));
        if ($others !== []) {
            $this->sendAllClears($state, $config);
        }
        return true;
    }

    // ------------------------------------------------------------------
    // Small helpers
    // ------------------------------------------------------------------

    public function saveState(array $state): void
    {
        $this->store->write('state', $state);
    }

    public function nextSlot(array $config, int $after): int
    {
        return Schedule::nextSlot(
            $after,
            (string) ($config['timezone'] ?? 'UTC'),
            (int) ($config['slot_dow'] ?? 1),
            (int) ($config['slot_hour'] ?? 9)
        );
    }

    /** @return string[] */
    public function ownerAddresses(array $config): array
    {
        return array_values(array_filter([(string) ($config['owner_email'] ?? ''), (string) ($config['backup_email'] ?? '')]));
    }

    public function url(array $config, string $page, array $query = []): string
    {
        return rtrim((string) ($config['base_url'] ?? ''), '/') . '/' . $page . ($query ? '?' . http_build_query($query) : '');
    }

    private function confirmedNok(): ?array
    {
        $nok = $this->contacts->nok();
        return $nok !== null && $nok['status'] === 'confirmed' ? $nok : null;
    }

    private function verifyEmail(array $config, array $state, array $nok, string $token, int $fireAt, bool $reminder): Email
    {
        return Messages::nokVerify(
            $config,
            $nok,
            Messages::vars($config, $state, $this->contacts->all(), $nok),
            $this->url($config, 'respond.php', ['t' => $token, 'a' => 'fine']),
            $this->url($config, 'respond.php', ['t' => $token, 'a' => 'wrong']),
            $fireAt,
            $reminder
        );
    }
}
