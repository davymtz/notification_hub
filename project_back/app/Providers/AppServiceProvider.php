<?php

namespace App\Providers;

use App\Contracts\NotificationChannel;
use App\Services\Notifiers\EmailNotifier;
use App\Services\Notifiers\SlackNotifier;
use App\Services\Notifiers\TelegramNotifier;
use App\Services\NotificationRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NotificationRegistry::class, function ($app) {
            $registry = new NotificationRegistry($app);

            // Register notification channels
            $registry->register('slack', SlackNotifier::class);
            $registry->register('email', EmailNotifier::class);
            $registry->register('telegram', TelegramNotifier::class);

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
