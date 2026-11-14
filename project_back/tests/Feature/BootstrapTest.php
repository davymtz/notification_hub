<?php

namespace Tests\Feature;

use App\Services\NotificationRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BootstrapTest extends TestCase
{
    public function test_framework_services_and_notification_provider_boot(): void
    {
        $this->assertTrue($this->app->bound('files'));
        $registry = $this->app->make(NotificationRegistry::class);
        foreach (['email', 'slack', 'telegram'] as $channel) {
            $this->assertTrue($registry->has($channel));
        }
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_dispatch_preserves_documented_route(): void
    {
        $route = Route::getRoutes()->match(
            \Illuminate\Http\Request::create('/v1/notifications/dispatch', 'POST')
        );
        $this->assertSame('v1/notifications/dispatch', $route->uri());
        $this->assertSame(
            \App\Http\Controllers\V1\NotificationController::class.'@dispatch',
            $route->getActionName()
        );
    }
}
