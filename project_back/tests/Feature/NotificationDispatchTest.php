<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationJob;
use App\Services\NotificationRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test successful notification dispatch returns 202
     */
    public function test_dispatch_returns_202_with_valid_data(): void
    {
        Queue::fake();

        $response = $this->postJson('/v1/notifications/dispatch', [
            'event_type' => 'user.created',
            'channels' => ['email', 'slack'],
            'idempotency_key' => 'test-key-' . uniqid(),
            'payload' => [
                'email' => 'test@example.com',
                'title' => 'Test Notification',
                'message' => 'This is a test message',
            ],
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'status',
                'event_id',
                'channels_queued',
            ])
            ->assertJson([
                'status' => 'queued',
                'channels_queued' => ['email', 'slack'],
            ]);
    }

    /**
     * Test that jobs are dispatched for each channel
     */
    public function test_jobs_are_dispatched_for_each_channel(): void
    {
        Queue::fake();

        $response = $this->postJson('/v1/notifications/dispatch', [
            'event_type' => 'order.shipped',
            'channels' => ['email', 'slack'],
            'idempotency_key' => 'test-key-' . uniqid(),
            'payload' => [
                'email' => 'customer@example.com',
                'title' => 'Order Shipped',
                'message' => 'Your order has been shipped',
            ],
        ]);

        $response->assertStatus(202);

        Queue::assertPushed(SendNotificationJob::class, 2);
        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->channel === 'email';
        });
        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->channel === 'slack';
        });
    }

    /**
     * Test idempotency - duplicate requests return cached response
     */
    public function test_idempotency_prevents_duplicate_processing(): void
    {
        Queue::fake();

        $idempotencyKey = 'test-key-' . uniqid();
        $payload = [
            'event_type' => 'user.created',
            'channels' => ['email'],
            'idempotency_key' => $idempotencyKey,
            'payload' => [
                'email' => 'test@example.com',
                'title' => 'Test',
                'message' => 'Message',
            ],
        ];

        // First request
        $response1 = $this->postJson('/v1/notifications/dispatch', $payload);
        $response1->assertStatus(202);

        // Second request with same idempotency key
        $response2 = $this->postJson('/v1/notifications/dispatch', $payload);
        $response2->assertStatus(200)
            ->assertJson([
                'status' => 'duplicate',
                'duplicate' => true,
            ]);

        // Job should only be dispatched once
        Queue::assertPushed(SendNotificationJob::class, 1);
    }
}
