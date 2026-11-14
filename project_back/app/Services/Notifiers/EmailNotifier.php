<?php

namespace App\Services\Notifiers;

use App\Contracts\NotificationChannel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailNotifier implements NotificationChannel
{
    /**
     * Send notification via Email
     *
     * @param array $payload
     * @return bool
     * @throws \Exception
     */
    public function send(array $payload): bool
    {
        $email = $payload['email'] ?? null;

        if (empty($email)) {
            throw new \Exception("Email address is required for email notifications");
        }

        $subject = $payload['subject'] ?? $payload['title'] ?? 'Notification';
        $body = $payload['message'] ?? $payload['body'] ?? '';

        // For testing/development, we use Mailhog which is configured in docker-compose
        Mail::raw($body, function ($message) use ($email, $subject) {
            $message->to($email)
                ->subject($subject);
        });

        Log::info("Email notification sent", [
            'to' => $email,
            'subject' => $subject,
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
        return 'email';
    }
}
