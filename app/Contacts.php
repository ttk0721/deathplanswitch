<?php
declare(strict_types=1);

namespace DPS;

/**
 * The people the switch can write to, stored in contacts.json.
 *
 * Kinds: exactly one next of kin, an optional technical helper, and any
 * number of legal or other contacts. Every contact carries an editable role
 * label, so the list can match how a particular business is handed over.
 */
final class Contacts
{
    public const KINDS = [
        'nok' => 'Next of kin',
        'technical' => 'Technical helper',
        'legal' => 'Legal contact',
        'other' => 'Other contact',
    ];

    public function __construct(private Store $store)
    {
    }

    /** @return array<int, array> next of kin first, then technical, legal, other */
    public function all(): array
    {
        $list = array_values(array_filter((array) ($this->store->read('contacts')['contacts'] ?? []), 'is_array'));
        $order = array_flip(array_keys(self::KINDS));
        usort($list, static fn (array $a, array $b): int =>
            [$order[$a['kind']] ?? 9, $a['created_at'] ?? 0] <=> [$order[$b['kind']] ?? 9, $b['created_at'] ?? 0]);
        return $list;
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $contact) {
            if (($contact['id'] ?? '') === $id) {
                return $contact;
            }
        }
        return null;
    }

    public function nok(): ?array
    {
        foreach ($this->all() as $contact) {
            if ($contact['kind'] === 'nok') {
                return $contact;
            }
        }
        return null;
    }

    /**
     * The chain of trusted people, in the order they are asked. The next of kin is always first.
     * A file written before the chain existed has no "trusted" field anywhere: then the next of kin
     * alone is the chain, and nothing on disk changes until the owner edits the list.
     *
     * @return array<int, array> every trusted contact, confirmed or not
     */
    public function trusted(): array
    {
        $list = $this->all();
        $nok = null;
        $explicit = false;
        $flagged = [];
        foreach ($list as $contact) {
            if (array_key_exists('trusted', $contact)) {
                $explicit = true;
            }
            if (($contact['kind'] ?? '') === 'nok') {
                $nok ??= $contact;
            } elseif (($contact['trusted'] ?? false) === true) {
                $flagged[] = $contact;
            }
        }
        if (!$explicit) {
            return $nok === null ? [] : [$nok];
        }
        usort($flagged, static fn (array $a, array $b): int =>
            [(int) ($a['trusted_order'] ?? PHP_INT_MAX), $a['created_at'] ?? 0] <=> [(int) ($b['trusted_order'] ?? PHP_INT_MAX), $b['created_at'] ?? 0]);
        return $nok === null ? $flagged : array_merge([$nok], $flagged);
    }

    /** The trusted people who have confirmed, in order: the ones who are actually asked. */
    public function trustedConfirmed(): array
    {
        return array_values(array_filter($this->trusted(), static fn (array $c): bool => ($c['status'] ?? '') === 'confirmed'));
    }

    /** 1 for the first trusted person, and so on; null if this contact is not trusted. */
    public function trustedNumber(string $id): ?int
    {
        foreach ($this->trusted() as $i => $contact) {
            if (($contact['id'] ?? '') === $id) {
                return $i + 1;
            }
        }
        return null;
    }

    /**
     * Add a contact to the end of the chain, or take them off it. The next of kin is always on it.
     *
     * @throws \InvalidArgumentException when the contact is set not to be told in advance
     */
    public function setTrusted(string $id, bool $trusted): void
    {
        $this->store->locked(function () use ($id, $trusted): void {
            $contact = $this->find($id);
            if ($trusted && $contact !== null && self::isSilent($contact)) {
                throw new \InvalidArgumentException(t('{name} is set not to be told in advance, and trusted people have to know. Turn that option off first.', ['name' => (string) $contact['name']]));
            }
            $doc = $this->store->read('contacts');
            $list = array_values(array_filter((array) ($doc['contacts'] ?? []), 'is_array'));
            $next = 1;
            foreach ($list as $c) {
                $next = max($next, (int) ($c['trusted_order'] ?? 0) + 1);
            }
            foreach ($list as $i => $c) {
                if (($c['id'] ?? '') !== $id || ($c['kind'] ?? '') === 'nok') {
                    continue;
                }
                if ($trusted && ($c['trusted'] ?? false) !== true) {
                    $list[$i]['trusted'] = true;
                    $list[$i]['trusted_order'] = $next;
                } elseif (!$trusted) {
                    $list[$i]['trusted'] = false;
                    $list[$i]['trusted_order'] = null;
                }
            }
            $this->store->write('contacts', ['contacts' => $list]);
        });
    }

    /** Move a trusted person up ($by = -1) or down ($by = 1). Nobody can go above the next of kin. */
    public function move(string $id, int $by): void
    {
        $this->store->locked(function () use ($id, $by): void {
            $ids = array_column($this->trusted(), 'id');
            $at = array_search($id, $ids, true);
            $to = $at === false ? false : $at + ($by < 0 ? -1 : 1);
            if ($at === false || $at < 1 || $to < 1 || $to >= count($ids)) {
                return;
            }
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            $doc = $this->store->read('contacts');
            $list = array_values(array_filter((array) ($doc['contacts'] ?? []), 'is_array'));
            foreach ($list as $i => $c) {
                $n = array_search($c['id'] ?? '', $ids, true);
                if ($n !== false) {
                    $list[$i]['trusted'] = true;
                    $list[$i]['trusted_order'] = $n + 1;
                }
            }
            $this->store->write('contacts', ['contacts' => $list]);
        });
    }

    /** True for a contact the owner chose not to tell in advance: no invitation, no confirmation request. */
    public static function isSilent(array $contact): bool
    {
        return ($contact['silent'] ?? false) === true;
    }

    /**
     * Will this contact be sent their message when the switch fires? A confirmed contact will, and so
     * will a silent one, who is never asked to confirm. Someone who declined never is.
     */
    public static function receives(array $contact): bool
    {
        $status = (string) ($contact['status'] ?? '');
        return $status === 'confirmed' || (self::isSilent($contact) && $status !== 'declined');
    }

    /** @return array<int, array> everyone sent a message when the switch fires, in the usual order */
    public function recipients(): array
    {
        return array_values(array_filter($this->all(), [self::class, 'receives']));
    }

    /** Is this contact linked to a vault recipient, and so holds vault packages? */
    public function inVault(string $id): bool
    {
        foreach ((array) ($this->store->read('vault')['recipients'] ?? []) as $recipient) {
            if (is_array($recipient) && ($recipient['contact_id'] ?? null) === $id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Why this contact cannot be left untold until the switch fires, or null if they can be.
     * Anyone asked whether the owner is all right, and anyone holding vault packages, has to know in advance.
     */
    public function silentBlocker(array $contact): ?string
    {
        $id = (string) ($contact['id'] ?? '');
        if (($contact['kind'] ?? '') === 'nok') {
            return t('Your next of kin is asked first whether you are all right, so they have to know about the switch in advance.');
        }
        if (($contact['trusted'] ?? false) === true || ($id !== '' && $this->trustedNumber($id) !== null)) {
            return t('Trusted people are asked whether you are all right, so they have to know about the switch in advance. Remove them from trusted people first.');
        }
        if ($id !== '' && $this->inVault($id)) {
            return t('They hold vault packages, and opening those needs a code given to them in advance. Remove them from the vault first.');
        }
        return null;
    }

    /** @return array<int, array> contacts who have confirmed, and so will be written to */
    public function confirmed(): array
    {
        return array_values(array_filter($this->all(), static fn (array $c): bool => ($c['status'] ?? '') === 'confirmed'));
    }

    public function confirmedOfKind(string $kind): array
    {
        return array_values(array_filter($this->confirmed(), static fn (array $c): bool => $c['kind'] === $kind));
    }

    /** A new, unsaved contact with the starter wording for its kind. */
    public static function blank(string $kind): array
    {
        $kind = isset(self::KINDS[$kind]) ? $kind : 'other';
        $contact = [
            'id' => 'c_' . bin2hex(random_bytes(5)),
            'kind' => $kind,
            'role_label' => self::KINDS[$kind],
            'name' => '',
            'email' => '',
            'phone' => '',
            'pending_email' => null,
            'status' => 'unconfirmed',      // unconfirmed | confirmed | declined
            'token' => null,                // {hash, expires_at, purpose}
            'confirm_sent_at' => null,
            'confirmed_at' => null,
            'reconfirmed_at' => null,
            'reconfirm_asked_at' => null,
            'reconfirm_nudged_at' => null,
            'has_needs_at' => null,         // when the owner last ticked "has what they need"
            'silent' => false,              // true: never told in advance; their first message is the one sent when the switch fires
            'message' => Messages::defaults($kind),
            'created_at' => Clock::now(),
            'updated_at' => Clock::now(),
        ];
        // The next of kin is trusted number one; anyone else joins the chain when the owner adds them.
        $contact['trusted'] = $kind === 'nok';
        $contact['trusted_order'] = $kind === 'nok' ? 1 : null;
        if ($kind === 'nok') {
            $contact['verify_message'] = Messages::verifyDefault();
        }
        return $contact;
    }

    /**
     * @param bool $checkSilent refuse a contact set not to be told in advance who has to be told: checked
     *                          under the same lock as the write, so a change made meanwhile is seen
     * @throws \InvalidArgumentException when $checkSilent and the contact cannot be left untold
     */
    public function save(array $contact, bool $checkSilent = false): void
    {
        $this->store->locked(function () use ($contact, $checkSilent): void {
            if ($checkSilent && self::isSilent($contact)) {
                $fresh = $this->find((string) ($contact['id'] ?? '')) ?? [];
                $why = $this->silentBlocker(array_intersect_key($fresh, ['trusted' => true, 'trusted_order' => true]) + $contact);
                if ($why !== null) {
                    throw new \InvalidArgumentException($why);
                }
            }
            $doc = $this->store->read('contacts');
            $list = array_values(array_filter((array) ($doc['contacts'] ?? []), 'is_array'));
            $contact['updated_at'] = Clock::now();
            $found = false;
            foreach ($list as $i => $existing) {
                if (($existing['id'] ?? '') === $contact['id']) {
                    $list[$i] = $contact;
                    $found = true;
                }
            }
            if (!$found) {
                $list[] = $contact;
            }
            $this->store->write('contacts', ['contacts' => $list]);
        });
    }

    public function delete(string $id): void
    {
        $this->store->locked(function () use ($id): void {
            $doc = $this->store->read('contacts');
            $list = array_values(array_filter(
                (array) ($doc['contacts'] ?? []),
                static fn ($c): bool => is_array($c) && ($c['id'] ?? '') !== $id
            ));
            $this->store->write('contacts', ['contacts' => $list]);
        });
    }

    /** Find the contact holding this emailed token, if it is still valid. */
    public function forToken(string $plain): ?array
    {
        foreach ($this->all() as $contact) {
            if (is_array($contact['token'] ?? null) && Tokens::matches($plain, [$contact['token']])) {
                return $contact;
            }
        }
        return null;
    }

    /** The role label as it reads mid-sentence: "next of kin", "technical helper", or whatever the owner typed. */
    public static function role(array $contact): string
    {
        $label = trim((string) ($contact['role_label'] ?? ''));
        return match ($label) {
            'Next of kin' => t('next of kin'),
            'Technical helper', 'Technical 2IC' => t('technical helper'),
            'Legal contact' => t('legal contact'),
            'Other contact', '' => t('contact'),
            default => $label,
        };
    }

    /** A role label for display: the four built-in labels are translated, anything the owner typed is shown as typed. */
    public static function label(string $label): string
    {
        return match ($label) {
            'Next of kin' => t('Next of kin'),
            'Technical helper', 'Technical 2IC' => t('Technical helper'),
            'Legal contact' => t('Legal contact'),
            'Other contact' => t('Other contact'),
            default => $label,
        };
    }

    /** The name of a kind, for display. */
    public static function kindLabel(string $kind): string
    {
        return self::label(self::KINDS[$kind] ?? self::KINDS['other']);
    }

    /** "Role: Name, email, phone" for use in messages to other contacts. */
    public static function line(array $contact): string
    {
        $parts = array_filter([$contact['name'] ?? '', $contact['email'] ?? '', $contact['phone'] ?? '']);
        return (isset($contact['role_label']) ? self::label((string) $contact['role_label']) : t('Contact')) . ': ' . implode(', ', $parts);
    }

    /** "Name, email, phone" */
    public static function details(?array $contact): string
    {
        if ($contact === null) {
            return t('(none listed)');
        }
        return implode(', ', array_filter([$contact['name'] ?? '', $contact['email'] ?? '', $contact['phone'] ?? '']));
    }
}
