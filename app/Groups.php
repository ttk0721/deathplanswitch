<?php
declare(strict_types=1);

namespace DPS;

/**
 * Group channels, stored in groups.json: a shared place such as a work Slack
 * channel or a friends' Discord server that is sent one message, written by
 * the owner, when the switch fires. Nothing else ever goes to a group channel:
 * no reminder, no question, and no vault package.
 *
 * The one exception: when the owner checks in after the switch fired, a group
 * that had received the message is sent its correction message, once, unless
 * the owner turned that off for the group.
 *
 * A group is {id, name, channel, text, correction, correction_text, created_at,
 * updated_at}, where channel is a record from Channels::normalize() whose label
 * is the group's name.
 *
 * Unlike a contact's channel, which only points to an email, a group channel
 * carries the whole text. A text longer than the platform allows is split into
 * numbered parts, and each part is delivered once.
 *
 * Everyone in a group channel reads the text, so it may only use the owner's
 * own placeholders (PLACEHOLDERS), never one that names the contacts.
 */
final class Groups
{
    public const NAME_MAX = 120;
    /** The longest text the owner can save: ten Discord messages. */
    public const TEXT_MAX = 20000;
    /** Seconds between two test messages to one group channel. */
    public const TEST_EVERY = 60;
    /** The most group channels one switch keeps. */
    public const MAX = 50;
    /** The only placeholders a group text may use: about the owner, never about a contact. */
    public const PLACEHOLDERS = ['{owner_name}', '{days}', '{last_checkin_date}'];
    private const DOC = 'groups';

    public function __construct(private Store $store)
    {
    }

    /** @return array<int, array> oldest first */
    public function all(): array
    {
        $list = array_values(array_filter((array) ($this->store->read(self::DOC)['groups'] ?? []), 'is_array'));
        usort($list, static fn (array $a, array $b): int => ($a['created_at'] ?? 0) <=> ($b['created_at'] ?? 0));
        return $list;
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $group) {
            if (($group['id'] ?? '') === $id) {
                return $group;
            }
        }
        return null;
    }

    /** @throws \InvalidArgumentException when a new group would go over MAX */
    public function save(array $group): void
    {
        $this->store->locked(function () use ($group): void {
            $list = array_values(array_filter((array) ($this->store->read(self::DOC)['groups'] ?? []), 'is_array'));
            $group['updated_at'] = Clock::now();
            $found = false;
            foreach ($list as $i => $existing) {
                if (($existing['id'] ?? '') === $group['id']) {
                    $list[$i] = $group;
                    $found = true;
                }
            }
            if (!$found) {
                if (count($list) >= self::MAX) {
                    throw new \InvalidArgumentException(t('You can have at most {max} group channels.', ['max' => self::MAX]));
                }
                $list[] = $group;
            }
            $this->store->write(self::DOC, ['groups' => $list]);
        });
    }

    public function delete(string $id): void
    {
        $this->store->locked(function () use ($id): void {
            $list = array_values(array_filter(
                (array) ($this->store->read(self::DOC)['groups'] ?? []),
                static fn ($g): bool => is_array($g) && ($g['id'] ?? '') !== $id
            ));
            $this->store->write(self::DOC, ['groups' => $list]);
        });
    }

    /**
     * A group from the owner's form. On an edit an empty address keeps the saved one, and so does
     * the same address again: its webhook secret stays as it was.
     *
     * @return array{0: ?array, 1: list<string>} the group, or null and the reasons it was refused
     */
    public static function fromInput(?array $existing, string $name, string $type, string $url, string $text, ?bool $correction = null, ?string $correctionText = null): array
    {
        $errors = [];
        $name = self::cleanName($name);
        if ($name === '') {
            $errors[] = t('Enter a name for the group channel.');
        } elseif (mb_strlen($name) > self::NAME_MAX) {
            $errors[] = t('The name can be at most {max} characters.', ['max' => self::NAME_MAX]);
        }

        $old = is_array($existing['channel'] ?? null) ? $existing['channel'] : null;
        $url = trim($url);
        if ($old !== null && ($url === '' || ($url === (string) ($old['url'] ?? '') && $type === (string) ($old['type'] ?? '')))) {
            $channel = $old;
        } else {
            $channel = Channels::normalize(['type' => $type, 'url' => $url, 'label' => $name]);
            if ($channel === null) {
                $errors[] = t('That channel was not accepted. Use an Apprise address such as {examples}, or a webhook that starts with {scheme}. For a plain web address, choose Webhook.', ['examples' => 'discord://webhook_id/token, tgram://bot_token/chat_id or ntfy://topic', 'scheme' => 'https://']);
            }
        }

        $text = trim(str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $text));
        if ($text === '') {
            $errors[] = t('Write the message the group channel is sent.');
        } elseif (mb_strlen($text) > self::TEXT_MAX) {
            $errors[] = t('The message can be at most {max} characters.', ['max' => self::TEXT_MAX]);
        }

        // The correction: on unless turned off, and its text falls back to the default when left empty.
        $correction ??= ($existing['correction'] ?? true) !== false;
        $correctionText = trim(str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $correctionText ?? (string) ($existing['correction_text'] ?? '')));
        if ($correctionText === '') {
            $correctionText = self::defaultCorrection();
        } elseif (mb_strlen($correctionText) > self::TEXT_MAX) {
            $errors[] = t('The message can be at most {max} characters.', ['max' => self::TEXT_MAX]);
        }

        $refused = array_values(array_unique(array_merge(self::refusedPlaceholders($text), self::refusedPlaceholders($correctionText))));
        if ($refused !== []) {
            $errors[] = t('A group channel message cannot use {refused}: everyone in the channel would see it. Only these fill themselves in here: {allowed}.', [
                'refused' => implode(', ', $refused), 'allowed' => implode(', ', self::PLACEHOLDERS)]);
        }

        if ($errors !== []) {
            return [null, $errors];
        }
        $channel['label'] = mb_substr($name, 0, 60);
        return [[
            'id' => (string) ($existing['id'] ?? 'g_' . bin2hex(random_bytes(5))),
            'name' => $name,
            'channel' => $channel,
            'text' => $text,
            'correction' => $correction,
            'correction_text' => $correctionText,
            'created_at' => (int) ($existing['created_at'] ?? Clock::now()),
            'updated_at' => Clock::now(),
        ], []];
    }

    /** A name with no line break, tab or other control character in it. */
    public static function cleanName(string $name): string
    {
        return trim((string) preg_replace('/[\x00-\x1f\x7f]|\xc2[\x80-\x9f]/', ' ', $name));
    }

    /** The correction text a group gets unless the owner writes their own. */
    public static function defaultCorrection(): string
    {
        return t('Please disregard the earlier message from {owner}: it was sent by mistake, and {owner} is fine.', ['owner' => '{owner_name}']);
    }

    /** @return list<string> the placeholders in $text a group channel may not use */
    public static function refusedPlaceholders(string $text): array
    {
        $out = [];
        foreach (array_keys(Messages::PLACEHOLDERS) as $code) {
            if (!in_array($code, self::PLACEHOLDERS, true) && str_contains($text, $code)) {
                $out[] = $code;
            }
        }
        return $out;
    }

    /** The scheme of an Apprise address, in lower case, or '' for a webhook. */
    private static function scheme(array $channel): string
    {
        if (($channel['type'] ?? '') !== 'apprise') {
            return '';
        }
        $url = (string) ($channel['url'] ?? '');
        return strtolower(strpos($url, '://') !== false ? substr($url, 0, (int) strpos($url, '://')) : '');
    }

    /** The longest message the platform takes, counted as utf16() says. */
    public static function limit(array $channel): int
    {
        return match (self::scheme($channel)) {
            'discord' => 2000,
            'tgram', 'telegram' => 4096,
            'slack' => 40000,
            default => 4000,
        };
    }

    /**
     * Does the platform count its limit in UTF-16 code units rather than characters? Telegram does,
     * and Discord is counted so too, to be safe: an emoji is then two, never one.
     */
    public static function utf16(array $channel): bool
    {
        return in_array(self::scheme($channel), ['discord', 'tgram', 'telegram'], true);
    }

    /** The length of $text as a platform counts it. */
    public static function length(string $text, bool $utf16 = false): int
    {
        return $utf16 ? intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2) : mb_strlen($text);
    }

    /** The text with its placeholders filled in. Only the owner's own placeholders are ever filled in. */
    public static function message(array $group, array $vars): string
    {
        return self::fill((string) ($group['text'] ?? ''), $vars);
    }

    /** The correction text with its placeholders filled in, the same way. */
    public static function correction(array $group, array $vars): string
    {
        $text = trim((string) ($group['correction_text'] ?? ''));
        return self::fill($text !== '' ? $text : self::defaultCorrection(), $vars);
    }

    private static function fill(string $text, array $vars): string
    {
        return trim(Messages::fill($text, array_intersect_key($vars, array_flip(self::PLACEHOLDERS))));
    }

    /** @return list<string> the messages to send, in order */
    public static function parts(array $group, array $vars): array
    {
        $channel = (array) ($group['channel'] ?? []);
        return self::split(self::message($group, $vars), self::limit($channel), self::utf16($channel));
    }

    /**
     * Split a text into messages of at most $limit characters, or UTF-16 code units when $utf16.
     * More than one part are numbered " (1/3)" and so on, inside the limit. A break goes after a
     * paragraph if one is near enough, else after a sentence, else between words. A word longer
     * than a whole part is cut, but never inside a character as the reader sees it: an emoji
     * sequence or a letter with its accents stays whole.
     *
     * @return list<string>
     */
    public static function split(string $text, int $limit, bool $utf16 = false): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if (self::length($text, $utf16) <= $limit) {
            return [$text];
        }
        $chars = self::graphemes($text);
        $widths = array_map(static fn (string $g): int => self::length($g, $utf16), $chars);
        // The marker grows with the number of parts, so split again until the count fits its marker.
        $digits = 1;
        while (true) {
            $room = max(1, $limit - strlen(' (' . str_repeat('9', $digits) . '/' . str_repeat('9', $digits) . ')'));
            $chunks = self::chunks($chars, $widths, $room);
            if (strlen((string) count($chunks)) <= $digits) {
                break;
            }
            $digits = strlen((string) count($chunks));
        }
        $total = count($chunks);
        $out = [];
        foreach ($chunks as $i => $chunk) {
            $out[] = $chunk . ' (' . ($i + 1) . '/' . $total . ')';
        }
        return $out;
    }

    /** @return list<string> the text as the characters a reader sees (grapheme clusters) */
    private static function graphemes(string $text): array
    {
        if (function_exists('grapheme_str_split')) {
            $out = grapheme_str_split($text);
            if (is_array($out)) {
                return $out;
            }
        }
        $out = preg_split('/(\X)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        return is_array($out) ? $out : mb_str_split($text);
    }

    /**
     * @param list<string> $chars grapheme clusters
     * @param list<int> $widths the width of each, as the platform counts it
     * @return list<string> pieces at most $room wide
     */
    private static function chunks(array $chars, array $widths, int $room): array
    {
        $n = count($chars);
        $space = static fn (?string $c): bool => $c !== null && preg_match('/^\s$/u', $c) === 1;
        $out = [];
        $start = 0;
        while (array_sum(array_slice($widths, $start)) > $room) {
            // The first character that does not fit; a part always takes at least one.
            $end = $start;
            $used = 0;
            while ($end < $n && $used + $widths[$end] <= $room) {
                $used += $widths[$end];
                $end++;
            }
            $end = max($end, $start + 1);
            $floor = $start + intdiv($end - $start, 3); // a break this early would waste most of a part
            $cut = null;
            for ($i = $end; $i > $floor && $cut === null; $i--) {
                if (($chars[$i] ?? '') === "\n" && ($chars[$i - 1] ?? '') === "\n") {
                    $cut = $i - 1;
                }
            }
            for ($i = $end; $i > $floor && $cut === null; $i--) {
                if ($space($chars[$i] ?? null) && in_array($chars[$i - 1], ['.', '!', '?', '…'], true)) {
                    $cut = $i;
                }
            }
            for ($i = $end; $i > $start && $cut === null; $i--) {
                if ($space($chars[$i] ?? null)) {
                    $cut = $i;
                }
            }
            $cut ??= $end;
            $piece = trim(implode('', array_slice($chars, $start, $cut - $start)));
            if ($piece !== '') {
                $out[] = $piece;
            }
            $start = $cut;
            while ($start < $n && $space($chars[$start])) {
                $start++;
            }
        }
        $piece = trim(implode('', array_slice($chars, $start)));
        if ($piece !== '') {
            $out[] = $piece;
        }
        return $out;
    }

    /**
     * Send a short, neutral test message to a group channel now, at most once a minute per channel.
     *
     * @return array{ok: bool, message: string} what to tell the owner
     */
    public static function sendTest(Store $store, Notifier $notifier, array $group): array
    {
        $id = (string) ($group['id'] ?? '');
        $wait = $store->locked(static function () use ($store, $id): int {
            $mail = $store->read('mail');
            $last = (int) ($mail['group_tests'][$id] ?? 0);
            if ($last > 0 && Clock::now() - $last < self::TEST_EVERY) {
                return self::TEST_EVERY - (Clock::now() - $last);
            }
            $mail['group_tests'][$id] = Clock::now();
            $store->write('mail', $mail);
            return 0;
        });
        if ($wait > 0) {
            return ['ok' => false, 'message' => t('A test was sent less than a minute ago. Try again in a minute.')];
        }
        $channel = ['label' => (string) ($group['name'] ?? '')] + (array) ($group['channel'] ?? []);
        try {
            $notifier->notify($channel, new Notice('', t('This is a test message. Nothing has happened and nothing needs to be done.'), null), 'test');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => t('The test failed: {reason}', ['reason' => $e->getMessage() !== '' ? $e->getMessage() : t('unknown error')])];
        }
        return ['ok' => true, 'message' => t('Test sent to {name}.', ['name' => (string) ($group['name'] ?? '')])];
    }
}
