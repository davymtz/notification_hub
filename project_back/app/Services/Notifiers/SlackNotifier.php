<?php

namespace App\Services\Notifiers;

use App\Contracts\NotificationChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackNotifier implements NotificationChannel
{
    private ?string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.slack.webhook_url');
    }

    /**
     * Send notification to Slack
     *
     * @param array $payload
     * @return bool
     * @throws \Exception
     */
    public function send(array $payload): bool
    {
        // If no webhook URL is configured, simulate the send (for testing/development)
        if (empty($this->webhookUrl) || str_contains($this->webhookUrl, 'YOUR/WEBHOOK')) {
            Log::info("[MOCK] Slack notification would be sent", [
                'payload' => $payload,
            ]);
            return true;
        }

        // Real implementation
        $response = Http::post($this->webhookUrl, [
            'text' => $payload['message'] ?? $payload['title'] ?? 'Notification',
            'blocks' => $this->formatBlocks($payload),
        ]);

        if (!$response->successful()) {
            throw new \Exception("Slack API error: {$response->status()} - {$response->body()}");
        }

        return true;
    }

    /**
     * Format Slack blocks for rich formatting
     *
     * @param array $payload
     * @return array
     */
    private function formatBlocks(array $payload): array
    {
        $title = $payload['title'] ?? '';
        $message = $payload['message'] ?? '';
        $text = trim(($title !== '' ? "*{$title}*\n" : '').$message);

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $text !== '' ? $text : 'Notification',
                ],
            ],
        ];

        if (isset($payload['fields'])) {
            $fields = [];
            foreach ($payload['fields'] as $label => $value) {
                $fields[] = [
                    'type' => 'mrkdwn',
                    'text' => "*{$label}:*\n{$value}",
                ];
            }
            $blocks[] = [
                'type' => 'section',
                'fields' => $fields,
            ];
        }

        return $blocks;
    }

    /**
     * Get the channel name
     *
     * @return string
     */
    public function getChannelName(): string
    {
        return 'slack';
    }
}
