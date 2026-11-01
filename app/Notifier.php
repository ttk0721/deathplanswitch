<?php
declare(strict_types=1);

namespace DPS;

/** Delivers a Notice to one channel. */
interface Notifier
{
    /**
     * @param array $channel a channel record (see Channels)
     * @param string $event what happened, for example 'checkin' or 'test'
     * @throws \RuntimeException on failure
     */
    public function notify(array $channel, Notice $notice, string $event): void;
}
