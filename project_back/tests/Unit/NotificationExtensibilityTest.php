<?php

namespace Tests\Unit;

use App\Contracts\NotificationChannel;
use App\Services\NotificationRegistry;
use App\Services\NotificationRouterService;
use App\DTO\NotificationDTO;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationExtensibilityTest extends TestCase
{
    /**
     * Test that adding a new channel only requires registration
     * This test demonstrates extensibility - no changes to Router/Controller needed
     */
    public function test_new_channel_can_be_added_via_registration_only(): void
    {
        // Create a fake notifier for testing extensibility
        $fakeNotifier = new class implements NotificationChannel {
            public bool $wasCalled = false;
            
            public function send(array $payload): bool
            {
                $this->wasCalled = true;
                return true;
            }
            
            public function getChannelName(): string
            {
                return 'fake_channel';
            }
        };

        // Register the new channel dynamically
        $registry = app(NotificationRegistry::class);
        $registry->register('fake_channel', get_class($fakeNotifier));

        // Bind the fake notifier in the container
        $this->app->bind(get_class($fakeNotifier), fn() => $fakeNotifier);

        // Verify the channel is registered
        $this->assertTrue($registry->has('fake_channel'));
        $this->assertContains('fake_channel', $registry->getRegisteredChannels());

        // Dispatch a notification to the new channel
        Queue::fake();

        $response = $this->postJson('/v1/notifications/dispatch', [
            'event_type' => 'test.event',
            'channels' => ['fake_channel'],
            'idempotency_key' => 'extensibility-test-' . uniqid(),
            'payload' => ['message' => 'Test'],
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'channels_queued' => ['fake_channel'],
            ]);

        // Verify the job was dispatched for the new channel
        Queue::assertPushed(SendNotificationJob::class, function ($job) {
            return $job->channel === 'fake_channel'
                && $job->dto->payload === ['message' => 'Test'];
        });
    }

    /**
     * Test that Registry resolves channels via container
     */
    public function test_registry_resolves_via_container(): void
    {
        $registry = app(NotificationRegistry::class);

        // Verify existing channels can be resolved
        $slackNotifier = $registry->resolve('slack');
        $this->assertInstanceOf(NotificationChannel::class, $slackNotifier);
        $this->assertEquals('slack', $slackNotifier->getChannelName());

        $emailNotifier = $registry->resolve('email');
        $this->assertInstanceOf(NotificationChannel::class, $emailNotifier);
        $this->assertEquals('email', $emailNotifier->getChannelName());

        $telegramNotifier = $registry->resolve('telegram');
        $this->assertInstanceOf(NotificationChannel::class, $telegramNotifier);
        $this->assertEquals('telegram', $telegramNotifier->getChannelName());
    }

    /**
     * Test that unregistered channel throws exception
     */
    public function test_registry_throws_for_unregistered_channel(): void
    {
        $registry = app(NotificationRegistry::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification channel [nonexistent] is not registered');

        $registry->resolve('nonexistent');
    }
}
