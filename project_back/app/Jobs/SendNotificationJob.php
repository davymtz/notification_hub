<?php

namespace App\Jobs;

use App\Contracts\NotificationChannel;
use App\DTO\NotificationDTO;
use App\Services\NotificationRegistry;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts
     */
    public int $tries = 3;

    /**
     * Backoff strategy between retries (seconds)
     */
    public array $backoff = [10, 30, 60];

    /**
     * Create a new job instance
     */
    public function __construct(
        public NotificationDTO $dto,
        public string $channel
    ) {}

    /**
     * Execute the job
     */
    public function handle(NotificationRegistry $registry): void
    {
        try {
            $notifier = $registry->resolve($this->channel);
            $notifier->send($this->dto->payload);

            Log::info("Notification sent successfully", [
                'channel' => $this->channel,
                'event_id' => $this->dto->eventId,
                'event_type' => $this->dto->eventType,
            ]);
        } catch (Throwable $e) {
            Log::error("Failed to send notification", [
                'channel' => $this->channel,
                'event_id' => $this->dto->eventId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying
     */
    public function backoff(): array
    {
        return $this->backoff;
    }

    /**
     * Determine the time at which the job should timeout
     */
    public function retryUntil(): DateTime
    {
        return now()->addMinutes(10);
    }

    /**
     * Handle a job failure
     */
    public function failed(Throwable $exception): void
    {
        Log::error("Notification job failed permanently", [
            'channel' => $this->channel,
            'event_id' => $this->dto->eventId,
            'error' => $exception->getMessage(),
        ]);
    }
}
