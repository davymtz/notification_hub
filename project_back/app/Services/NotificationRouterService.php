<?php

namespace App\Services;

use App\DTO\NotificationDTO;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class NotificationRouterService
{
    public function __construct(
        private NotificationRegistry $registry
    ) {}

    /**
     * Dispatch notification jobs for each channel
     *
     * @param NotificationDTO $dto
     * @return array
     */
    public function dispatch(NotificationDTO $dto): array
    {
        $queuedChannels = [];
        $eventId = $dto->eventId;

        // Check idempotency
        $cacheKey = "notification:{$dto->idempotencyKey}";
        
        if (Cache::has($cacheKey)) {
            // Already processed, return cached response
            return [
                'status' => 'duplicate',
                'event_id' => $eventId,
                'channels_queued' => Cache::get($cacheKey),
                'duplicate' => true,
            ];
        }

        // Dispatch a job for each channel
        foreach ($dto->channels as $channel) {
            if (!$this->registry->has($channel)) {
                continue; // Skip unsupported channels (validation should catch this)
            }

            SendNotificationJob::dispatch($dto, $channel);
            $queuedChannels[] = $channel;
        }

        // Cache the idempotency key for 24 hours
        Cache::put($cacheKey, $queuedChannels, now()->addHours(24));

        return [
            'status' => 'queued',
            'event_id' => $eventId,
            'channels_queued' => $queuedChannels,
        ];
    }
}
