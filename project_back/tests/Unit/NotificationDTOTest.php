<?php

namespace Tests\Unit;

use App\DTO\NotificationDTO;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class NotificationDTOTest extends TestCase
{
    public function test_missing_event_id_is_generated_once(): void
    {
        $dto = new NotificationDTO('test.event', ['email'], 'dto-test', ['email' => 'test@example.com']);

        $this->assertTrue(Str::isUuid($dto->eventId));
        $this->assertSame($dto->eventId, $dto->toArray()['event_id']);
    }

    public function test_supplied_event_id_is_preserved(): void
    {
        $id = '5acc98af-8458-4d47-bbeb-3a72cdc964db';
        $dto = NotificationDTO::fromArray([
            'event_type' => 'test.event',
            'channels' => ['email'],
            'idempotency_key' => 'dto-test',
            'payload' => ['email' => 'test@example.com'],
            'event_id' => $id,
        ]);

        $this->assertSame($id, $dto->eventId);
    }
}
