<?php

namespace App\Services;

use App\Contracts\NotificationChannel;
use Illuminate\Container\Container;
use InvalidArgumentException;

class NotificationRegistry
{
    private array $channels = [];

    public function __construct(
        private Container $container
    ) {}

    /**
     * Register a notification channel implementation
     *
     * @param string $channelName
     * @param string|NotificationChannel $implementation
     * @return void
     */
    public function register(string $channelName, string $implementation): void
    {
        $this->channels[$channelName] = $implementation;
    }

    /**
     * Resolve a notification channel by name
     *
     * @param string $channelName
     * @return NotificationChannel
     * @throws InvalidArgumentException
     */
    public function resolve(string $channelName): NotificationChannel
    {
        if (!isset($this->channels[$channelName])) {
            throw new InvalidArgumentException(
                "Notification channel [{$channelName}] is not registered."
            );
        }

        return $this->container->make($this->channels[$channelName]);
    }

    /**
     * Check if a channel is registered
     *
     * @param string $channelName
     * @return bool
     */
    public function has(string $channelName): bool
    {
        return isset($this->channels[$channelName]);
    }

    /**
     * Get all registered channels
     *
     * @return array
     */
    public function getRegisteredChannels(): array
    {
        return array_keys($this->channels);
    }
}
