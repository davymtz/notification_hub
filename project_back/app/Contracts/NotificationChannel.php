<?php

namespace App\Contracts;

interface NotificationChannel
{
    /**
     * Send notification through this channel
     *
     * @param array $payload The notification payload
     * @return bool
     * @throws \Exception
     */
    public function send(array $payload): bool;

    /**
     * Get the channel name
     *
     * @return string
     */
    public function getChannelName(): string;
}
