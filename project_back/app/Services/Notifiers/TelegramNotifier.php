<?php

namespace App\Services\Notifiers;

use App\Contracts\NotificationChannel;
use Illuminate\Support\Facades\Log;

class TelegramNotifier implements NotificationChannel
{
    /**
     * Send notification via Telegram
     * This is a FAKE implementation for extensibility demonstration
     *
     * @param array $payload
     * @return bool
     */
    public function send(array $payload): bool
    {
        // FAKE implementation - just logs the notification
        Log::info("[FAKE] Telegram notification would be sent", [
            'chat_id' => $payload['chat_id'] ?? 'unknown',
            'message' => $payload['message'] ?? 'No message',
        ]);

        return true;
    }

    /**
     * Get the channel name
     *
     * @return string
     */
    public function getChannelName(): string
    {
        return 'telegram';
    }
}
