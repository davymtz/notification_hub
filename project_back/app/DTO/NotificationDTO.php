<?php

namespace App\DTO;

use Illuminate\Support\Str;

readonly class NotificationDTO
{
    public ?string $eventId;

    public function __construct(
        public string $eventType,
        public array $channels,
        public string $idempotencyKey,
        public array $payload,
        ?string $eventId = null,
    ) {
        $this->eventId = $eventId ?? (string) Str::uuid();
    }

    /**
     * Create DTO from validated request data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eventType: $data['event_type'],
            channels: $data['channels'],
            idempotencyKey: $data['idempotency_key'],
            payload: $data['payload'],
            eventId: $data['event_id'] ?? null,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'event_type' => $this->eventType,
            'channels' => $this->channels,
            'idempotency_key' => $this->idempotencyKey,
            'payload' => $this->payload,
            'event_id' => $this->eventId,
        ];
    }
}
