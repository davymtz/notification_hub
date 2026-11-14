<?php

namespace Tests\Unit;

use App\Services\Notifiers\SlackNotifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackNotifierTest extends TestCase
{
    public function test_missing_webhook_uses_local_mock_without_network(): void
    {
        config(['services.slack.webhook_url' => null]);
        Http::preventStrayRequests();
        Http::fake();

        $notifier = $this->app->make(SlackNotifier::class);

        $this->assertSame('slack', $notifier->getChannelName());
        $this->assertTrue($notifier->send(['message' => 'Local test']));
        Http::assertNothingSent();
    }

    public function test_empty_webhook_uses_local_mock_without_network(): void
    {
        config(['services.slack.webhook_url' => '']);
        Http::preventStrayRequests();
        Http::fake();

        $this->assertTrue($this->app->make(SlackNotifier::class)->send(['message' => 'Local test']));
        Http::assertNothingSent();
    }
}
