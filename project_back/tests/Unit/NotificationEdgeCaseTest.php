<?php

namespace Tests\Unit;

use App\Services\Notifiers\SlackNotifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationEdgeCaseTest extends TestCase
{
    public function test_real_slack_transport_accepts_a_message_without_title(): void
    {
        config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/test/poc/local']);
        Http::preventStrayRequests();
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);
        $this->assertTrue($this->app->make(SlackNotifier::class)->send(['message' => 'Message only']));
        Http::assertSent(fn ($request) => str_contains($request['blocks'][0]['text']['text'], 'Message only'));
    }

    public function test_real_slack_transport_accepts_a_title_without_message(): void
    {
        config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/test/poc/local']);
        Http::preventStrayRequests();
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);
        $this->assertTrue($this->app->make(SlackNotifier::class)->send(['title' => 'Title only']));
        Http::assertSent(fn ($request) => str_contains($request['blocks'][0]['text']['text'], 'Title only'));
    }

    public function test_slack_provider_failure_is_propagated_for_queue_retry(): void
    {
        config(['services.slack.webhook_url' => 'https://hooks.slack.com/services/test/poc/local']);
        Http::preventStrayRequests();
        Http::fake(['hooks.slack.com/*' => Http::response('temporarily unavailable', 503)]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Slack API error: 503');
        $this->app->make(SlackNotifier::class)->send(['title' => 'Test', 'message' => 'Test']);
    }

    public function test_malformed_channels_returns_422_instead_of_500(): void
    {
        Queue::fake();
        $this->postJson('/v1/notifications/dispatch', [
            'event_type' => 'review.invalid', 'channels' => 'email',
            'idempotency_key' => 'review-invalid-channels', 'payload' => ['email' => 'test@example.test'],
        ])->assertStatus(422)->assertJsonValidationErrors('channels');
        Queue::assertNothingPushed();
    }
}
