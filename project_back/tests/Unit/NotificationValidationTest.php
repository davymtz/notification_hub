<?php

namespace Tests\Unit;

use Tests\TestCase;

class NotificationValidationTest extends TestCase
{
    /**
     * Test validation fails with unsupported channel
     */
    public function test_validation_fails_with_unsupported_channel(): void
    {
        $response = $this->postJson('/v1/notifications/dispatch', [
            'event_type' => 'user.created',
            'channels' => ['invalid_channel'],
            'idempotency_key' => 'test-key-' . uniqid(),
            'payload' => [
                'message' => 'Test message',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['channels.0'])
            ->assertJson([
                'message' => 'The selected channels.0 is invalid.',
            ]);
    }

    /**
     * Test validation fails when event_type is missing
     */
    public function test_validation_fails_without_event_type(): void
    {
        $response = $this->postJson('/v1/notifications/dispatch', [
            'channels' => ['email'],
            'idempotency_key' => 'test-key',
            'payload' => ['email' => 'test@example.com'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event_type']);
    }

    /**
     * Test validation fails when channels array is empty
     */
    public function test_validation_fails_with_empty_channels(): void
    {
        $response = $this->postJson('/v1/notifications/dispatch', [
            'event_type' => 'user.created',
            'channels' => [],
            'idempotency_key' => 'test-key',
            'payload' => ['message' => 'Test'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['channels']);
    }

    /**
     * Test validation fails when email channel is selected but no email in payload
     */
    public function test_validation_fails_without_email_for_email_channel(): void
    {
        $response = $this->postJson('/v1/notifications/dispatch', [
            'event_type' => 'user.created',
            'channels' => ['email'],
            'idempotency_key' => 'test-key',
            'payload' => [
                'message' => 'Test message',
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payload.email']);
    }

    /**
     * Test validation fails when idempotency_key is missing
     */
    public function test_validation_fails_without_idempotency_key(): void
    {
        $response = $this->postJson('/v1/notifications/dispatch', [
            'event_type' => 'user.created',
            'channels' => ['slack'],
            'payload' => ['message' => 'Test'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    /**
     * Test validation fails when payload is missing
     */
    public function test_validation_fails_without_payload(): void
    {
        $response = $this->postJson('/v1/notifications/dispatch', [
            'event_type' => 'user.created',
            'channels' => ['slack'],
            'idempotency_key' => 'test-key',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payload']);
    }
}
