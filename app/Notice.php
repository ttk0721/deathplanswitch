<?php
declare(strict_types=1);

namespace DPS;

/**
 * The short message sent to a notification channel: the subject and the first
 * link of an email, never the message text.
 */
final class Notice
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $link
    ) {
    }

    public static function fromEmail(Email $email, string $to, bool $emailSent = true): self
    {
        $body = $emailSent
            ? "The full message was sent by email to {$to}."
            : "The email to {$to} could not be sent yet, so this notice is all that got through. It will be tried again.";
        return new self($email->subject, $body, $email->firstLink());
    }

    public function text(): string
    {
        return $this->body . ($this->link !== null ? "\n" . $this->link : '');
    }
}
