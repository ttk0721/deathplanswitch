<?php
declare(strict_types=1);

namespace DPS;

/**
 * Every email the app sends, in one file.
 *
 * Two sorts of wording live here:
 *
 *  - Starter text for the messages each contact receives when the switch
 *    fires. The owner edits these. They are a convenience, not advice.
 *  - Fixed wording for the app's own emails (check-ins, confirmations,
 *    false alarms), which must stay accurate whatever the owner writes.
 */
final class Messages
{
    /**
     * Starter text carries a marker like "[write here: ...]" wherever the
     * owner has to supply a pointer. The switch will not arm while one is
     * left in a message, so the starter text can never be sent as it stands.
     */
    public const MARKER = '/\[write here:[^\]]*\]/i';

    public const PLACEHOLDERS = [
        '{owner_name}' => 'Your name',
        '{your_name}' => 'The name of the person receiving the message',
        '{days}' => 'Whole days since your last check-in',
        '{last_checkin_date}' => 'The date of your last check-in',
        '{nok_details}' => 'Next of kin: name, email, phone',
        '{technical_details}' => 'Technical contact: name, email, phone',
        '{contacts_list}' => 'Everyone else on your list, one per line',
    ];

    /** @return array{subject: string, body: string} */
    public static function defaults(string $kind): array
    {
        $sep = "\n\n";
        return match ($kind) {
            'nok' => [
                'subject' => t('{owner_name} has stopped checking in: what happens now'),
                'body' => t('{owner_name} set up Death Plan Switch so that the right people would be told if they became unavailable. They have not checked in for {days} days, so the people they chose have now been sent their instructions.')
                    . $sep . t('Those people are:') . "\n{contacts_list}"
                    . $sep . t('The documents that give you authority to act are kept here: [write here: where the authority documents are kept, and who to call]'),
            ],
            'technical' => [
                'subject' => t('{owner_name} has stopped checking in: please read'),
                'body' => t('{owner_name} set up Death Plan Switch so that you would be told if they became unavailable. That has now happened.')
                    . $sep . t('They asked you to keep their servers, accounts or projects running. The instructions and access details are here: [write here: where the handover document is, how to obtain its password, and what needs attention first]')
                    . $sep . t('Next of kin:') . ' {nok_details}',
            ],
            'legal' => [
                'subject' => t('Death Plan Switch has fired for {owner_name}'),
                'body' => t('{owner_name} listed you as their legal contact in Death Plan Switch. The switch has fired because they have not checked in and their next of kin has not confirmed they are well.')
                    . $sep . t('Next of kin:') . " {nok_details}\n" . t('Technical contact:') . ' {technical_details}',
            ],
            default => [
                'subject' => t('{owner_name} has stopped checking in'),
                'body' => t('{owner_name} listed you as a contact in Death Plan Switch, so that you would be told if they became unavailable. That has now happened: they have not checked in for {days} days.')
                    . $sep . t('[write here: what this person needs to know or do]')
                    . $sep . t('Next of kin:') . ' {nok_details}',
            ],
        };
    }

    /** The question put to the next of kin at the verify stage. */
    public static function verifyDefault(): array
    {
        return [
            'subject' => t('Is {owner_name} all right?'),
            'body' => t('{owner_name} hasn\'t checked in with Death Plan Switch for {days} days. You\'re listed as their next of kin. Is everything all right?'),
        ];
    }

    /** The question put to a trusted person who is not the next of kin. */
    public static function verifyTrusted(): array
    {
        return [
            'subject' => t('Is {owner_name} all right?'),
            'body' => t('{owner_name} hasn\'t checked in with Death Plan Switch for {days} days. You\'re one of the people they trust to be asked. Is everything all right?'),
        ];
    }

    public static function hasMarker(string $text): bool
    {
        return preg_match(self::MARKER, $text) === 1;
    }

    /** A gentle check for the "pointers only" rule. It cannot be exact, so it only ever warns. */
    public static function looksLikeLogin(string $text): bool
    {
        return preg_match('/\b(pass(word|phrase|code)?|pwd|pin|login|username)\s*(is|=|:)\s*\S+/i', $text) === 1;
    }

    public static function fill(string $text, array $vars): string
    {
        return strtr($text, $vars);
    }

    /** Values for the placeholders, as they stand right now, for one recipient. */
    public static function vars(array $config, array $state, array $contacts, ?array $recipient): array
    {
        $last = $state['last_checkin_at'] ?? null;
        $days = $last ? max(0, (int) floor((Clock::now() - $last) / Schedule::DAY)) : 0;

        $nok = null;
        $technical = null;
        $others = [];
        foreach ($contacts as $contact) {
            if (($contact['status'] ?? '') !== 'confirmed') {
                continue;
            }
            if ($contact['kind'] === 'nok') {
                $nok ??= $contact;
            }
            if ($contact['kind'] === 'technical') {
                $technical ??= $contact;
            }
            if ($recipient === null || $contact['id'] !== $recipient['id']) {
                $others[] = Contacts::line($contact);
            }
        }

        return [
            '{owner_name}' => (string) ($config['owner_name'] ?? ''),
            '{your_name}' => (string) ($recipient['name'] ?? ''),
            '{days}' => (string) $days,
            '{last_checkin_date}' => $last ? when($last, (string) ($config['timezone'] ?? 'UTC'), 'letter_date') : t('an unknown date'),
            '{nok_details}' => Contacts::details($nok),
            '{technical_details}' => Contacts::details($technical),
            '{contacts_list}' => $others ? implode("\n", $others) : t('(nobody else is listed)'),
        ];
    }

    // ---------------------------------------------------------------- owner

    public static function testCode(string $code): Email
    {
        return (new Email(t('Death Plan Switch: your confirmation code is {code}', ['code' => $code])))
            ->p(t('Your Death Plan Switch can send email. To confirm this address reaches you, enter this code on the page you have open:'))
            ->p($code)
            ->note(t('The code works for 30 minutes. If you did not ask for it, you can ignore this email.'));
    }

    public static function smtpTest(): Email
    {
        return (new Email(t('Death Plan Switch: mail settings test')))
            ->p(t('This is a test from your Death Plan Switch. If you are reading it, the new mail settings work and have been saved.'));
    }

    /**
     * The weekly check-in, and its reminders.
     *
     * @param int $number 0 for the weekly email, 1 for the first reminder, 2 for the last
     * @param string[] $warnings things on the dashboard that need attention
     */
    public static function checkin(array $config, string $url, int $number, int $verifyAt, ?array $nok, array $warnings): Email
    {
        $tz = (string) ($config['timezone'] ?? 'UTC');
        $who = $nok['name'] ?? t('your next of kin');
        $deadline = self::at($verifyAt, $tz);
        $vars = ['deadline' => $deadline, 'who' => $who];

        if ($number === 0) {
            // The date keeps each week's email distinct, so mail apps do not fold it into last week's.
            $email = (new Email(t('Check in with Death Plan Switch: {date}', ['date' => when(Clock::now(), $tz, 'letter_day')])))
                ->p(t('This is your weekly check-in. Open the page and press the button to confirm you are here.'))
                ->link(t('Open the check-in page'), $url)
                ->p(t('Opening the page is not enough: press the button. If you have not checked in by {deadline}, {who} will be asked whether everything is all right.', $vars));
        } elseif ($number === 1) {
            $email = (new Email(t('Reminder: check in with Death Plan Switch')))
                ->p(t('You have not checked in this week. Open the page and press the button to confirm you are here.'))
                ->link(t('Open the check-in page'), $url)
                ->p(t('If you have not checked in by {deadline}, {who} will be asked whether everything is all right.', $vars));
        } else {
            $email = (new Email(t('Last reminder: check in with Death Plan Switch')))
                ->p(t('This is the last reminder. If you have not checked in by {deadline}, {who} will be asked whether everything is all right.', $vars))
                ->link(t('Open the check-in page'), $url);
        }

        $status = t('Your switch is armed.');
        if ($warnings) {
            $status .= ' ' . (count($warnings) === 1
                    ? t('One thing needs your attention:')
                    : t('{count} things need your attention:', ['count' => count($warnings)]))
                . "\n- " . implode("\n- ", $warnings);
        }
        return $email->note($status . "\n" . t('If this weekly email ever stops arriving, your switch has stopped working. Check your server.'));
    }

    public static function ownerVerifyStarted(array $config, string $url, ?array $nok, int $fireAt): Email
    {
        $who = $nok['name'] ?? t('Your next of kin');
        $deadline = self::at($fireAt, (string) ($config['timezone'] ?? 'UTC'));
        return (new Email(t('Death Plan Switch has asked your next of kin if you are all right')))
            ->p(t('You did not check in, so {who} has now been asked whether everything is all right.', ['who' => $who]))
            ->p(t('You can still check in. If you do, they will be told it was a false alarm.'))
            ->link(t('Open the check-in page'), $url)
            ->p(t('If neither of you confirms you are well by {deadline}, your other contacts will receive their messages.', ['deadline' => $deadline]));
    }

    /** @param string[] $names who has been written to */
    public static function ownerFired(string $url, array $names): Email
    {
        return (new Email(t('Death Plan Switch has fired')))
            ->p(t('Your switch has fired. Messages have been sent to: {names}.', ['names' => implode(', ', $names)]))
            ->p(t('If you are reading this, press the button on this page. Everyone who was contacted will be told it was a false alarm.'))
            ->link(t('Tell them I am here'), $url);
    }

    public static function ownerArmed(array $config, int $firstEmailAt): Email
    {
        $first = self::at($firstEmailAt, (string) ($config['timezone'] ?? 'UTC'));
        return (new Email(t('Your Death Plan Switch is armed')))
            ->p(t('Your switch is armed. The first check-in email arrives on {first}, and one follows at the same time every week.', ['first' => $first]))
            ->p(t('Each email links to a page with a button. Pressing the button is the check-in.'))
            ->p(t('If you miss one: you get reminders for 72 hours, then your next of kin is asked whether everything is all right, and 72 hours after that your other contacts receive their messages.'))
            ->note(t('If the weekly email ever stops arriving, your switch has stopped working. Check your server.'));
    }

    public static function ownerDisarmed(string $reason): Email
    {
        return (new Email(t('Your Death Plan Switch is no longer armed')))
            ->p(t('Your switch has been disarmed.') . ' ' . $reason)
            ->p(t('While it is disarmed, no check-in emails are sent and nobody will be contacted. Sign in to arm it again.'));
    }

    public static function ownerPauseEnded(array $config, int $nextAt): Email
    {
        $next = self::at($nextAt, (string) ($config['timezone'] ?? 'UTC'));
        return (new Email(t('Your Death Plan Switch is active again')))
            ->p(t('The pause has ended and your switch is armed. Your next check-in email arrives on {next}.', ['next' => $next]));
    }

    /** @param bool $othersAsked other trusted people were asked before this one answered */
    public static function ownerVouched(array $config, ?array $nok, int $nextAt, bool $othersAsked = false): Email
    {
        $who = $nok['name'] ?? t('Your next of kin');
        $next = self::at($nextAt, (string) ($config['timezone'] ?? 'UTC'));
        $email = new Email(t('{who} confirmed you are all right', ['who' => $who]));
        if ($othersAsked) {
            $email->p(t('You missed this week\'s check-in, so the people you trust were asked in turn whether everything is all right. {who} answered that all is fine, and nobody else will be asked.', ['who' => $who]))
                ->p(t('The other trusted people who were asked before them have been told it was a false alarm.'));
        } else {
            $email->p(t('You missed this week\'s check-in, so {who} was asked whether everything is all right. They answered that all is fine, and nobody else has been contacted.', ['who' => $who]));
        }
        return $email->p(t('Your next check-in email arrives on {next}.', ['next' => $next]));
    }

    public static function ownerContactAnswered(array $contact, bool $accepted, string $extra = ''): Email
    {
        $name = $contact['name'] ?: $contact['email'];
        $role = Contacts::role($contact);
        $vars = ['name' => $name, 'role' => $role];
        $tail = $extra !== '' ? ' ' . $extra : '';
        if ($accepted) {
            return (new Email(t('{name} has confirmed as your {role}', $vars)))
                ->p(t('{name} confirmed their email address and their role as your {role}.', $vars) . $tail);
        }
        return (new Email(t('{name} has declined to be your {role}', $vars)))
            ->p(t('{name} declined, and will not be contacted by your switch. Sign in to choose someone else.', $vars) . $tail);
    }

    public static function ownerAnnualReview(string $url): Email
    {
        return (new Email(t('Death Plan Switch: your yearly review is due')))
            ->p(t('Once a year, check that each of your contacts still has what they need: the right document, the right password, the right phone number.'))
            ->link(t('Sign in to review your contacts'), $url);
    }

    public static function ownerDataExposed(string $url): Email
    {
        return (new Email(t('Death Plan Switch: your data folder can be read from the web')))
            ->p(t('A routine check found that files in the data folder of your Death Plan Switch can be opened in a browser. Those files hold your contacts and their messages.'))
            ->p(t('This usually happens after a server move or a web server change. The README explains how to block access. Fix it as soon as you can.'))
            ->link(t('Sign in'), $url);
    }

    // -------------------------------------------------------------- contacts

    public static function contactConfirm(array $config, array $contact, string $url, string $purpose): Email
    {
        $owner = (string) $config['owner_name'];
        $role = Contacts::role($contact);
        $vars = ['owner' => $owner, 'role' => $role];

        if ($purpose === 'reconfirm') {
            $email = (new Email(t('Still happy to be {owner}\'s {role}?', $vars)))
                ->p(t('About a year ago you agreed to be {owner}\'s {role} in Death Plan Switch. Please confirm that this email address still reaches you and that you are still willing.', $vars));
        } else {
            $email = (new Email(t('{owner} has listed you as their {role}', $vars)))
                ->p(t('{owner} uses Death Plan Switch, a small app on their own server. Every week it asks {owner} to check in. If they stop, it emails the people they have chosen. They have listed you as their {role}.', $vars));
        }

        if ($contact['kind'] === 'nok') {
            $email->p(t('What this means for you: if {owner} misses a check-in and three days of reminders, you would get one email asking whether everything is all right, with two buttons to answer.', $vars));
        } else {
            $email->p(t('What this means for you: if {owner} stops checking in and their next of kin cannot confirm they are well, you would get one email from this address telling you what {owner} needs you to do.', $vars));
        }

        return $email
            ->link(t('Open the confirmation page'), $url)
            ->p(t('To make sure a future message is not mistaken for spam, add this email address to your contacts. If you have questions, reply to this email and it will reach {owner}.', $vars))
            ->note(t('This link works for 30 days. If you do nothing, you will not be contacted again unless {owner} sends a new request.', $vars));
    }

    public static function nokVerify(array $config, array $contact, array $vars, string $fineUrl, string $wrongUrl, int $fireAt, bool $reminder): Email
    {
        $owner = (string) $config['owner_name'];
        $message = $contact['verify_message'] ?? (($contact['kind'] ?? '') === 'nok' ? self::verifyDefault() : self::verifyTrusted());
        $deadline = self::at($fireAt, (string) ($config['timezone'] ?? 'UTC'))
            . ' (' . self::zoneLabel((string) ($config['timezone'] ?? 'UTC')) . ')';

        $subject = self::fill((string) $message['subject'], $vars);
        $email = (new Email($reminder ? t('Reminder: {subject}', ['subject' => $subject]) : $subject))
            ->prose(self::fill((string) $message['body'], $vars))
            ->link(t('All fine'), $fineUrl)
            ->link(t('Something\'s wrong'), $wrongUrl)
            ->p(t('Each link opens a page where you confirm your answer. If neither you nor {owner} answers by {deadline}, the other people {owner} chose will be sent their instructions.', ['owner' => $owner, 'deadline' => $deadline]))
            ->p(t('You can use this link until the matter is settled.'));

        return $email->note(self::footer($config, $vars));
    }

    public static function fired(array $config, array $contact, array $vars): Email
    {
        $message = $contact['message'] ?? self::defaults((string) $contact['kind']);
        return (new Email(self::fill((string) $message['subject'], $vars)))
            ->prose(self::fill((string) $message['body'], $vars))
            ->note(self::footer($config, $vars));
    }

    public static function vaultInvite(array $config, string $url): Email
    {
        $owner = (string) $config['owner_name'];
        return (new Email(t('{owner} asks you to set a password for their vault', ['owner' => $owner])))
            ->p(t('{owner} left some things for you in a private vault. You can set a password of your own, so you do not have to rely on the code card you were given.', ['owner' => $owner]))
            ->p(t('You will need the code on that card once, to set the password.'))
            ->link(t('Set your password'), $url)
            ->note(t('This link works once, within 30 days.'));
    }

    /** To people who were asked, when another trusted person has confirmed the owner is all right. */
    public static function trustedClear(array $config, string $by): Email
    {
        $owner = (string) $config['owner_name'];
        return (new Email(t('False alarm: {owner} is all right', ['owner' => $owner])))
            ->p($by === ''
                ? t('Someone {owner} trusts has confirmed that {owner} is all right. Nothing more is needed from you. Thank you.', ['owner' => $owner])
                : t('{name} has confirmed that {owner} is all right. Nothing more is needed from you. Thank you.', ['name' => $by, 'owner' => $owner]));
    }

    public static function allClear(array $config): Email
    {
        $owner = (string) $config['owner_name'];
        return (new Email(t('False alarm: {owner} has checked in', ['owner' => $owner])))
            ->p(t('{owner} has checked in with Death Plan Switch. The earlier message was a false alarm, and you do not need to do anything.', ['owner' => $owner]))
            ->p(t('{owner} will be in touch. You can reply to this email to reach them.', ['owner' => $owner]));
    }

    private static function footer(array $config, array $vars): string
    {
        return t('Death Plan Switch is software {owner} runs on their own server. It sent this automatically because {owner} last checked in on {date} and has not answered its emails since. It cannot know why.', [
            'owner' => (string) $config['owner_name'],
            'date' => $vars['{last_checkin_date}'],
        ]);
    }

    /** A date and time for the owner's emails. Polish uses numbers, so no English day or month names appear. */
    private static function at(int $ts, string $tz): string
    {
        return when($ts, $tz, 'long');
    }

    private static function zoneLabel(string $tz): string
    {
        try {
            return (new \DateTimeImmutable('now', new \DateTimeZone($tz)))->format('T') . ', ' . $tz;
        } catch (\Throwable) {
            return 'UTC';
        }
    }
}
