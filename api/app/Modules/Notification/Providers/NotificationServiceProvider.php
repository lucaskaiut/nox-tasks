<?php

namespace App\Modules\Notification\Providers;

use App\Modules\Notification\Services\ChannelRegistry;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelRegistry::class, fn ($app) => new ChannelRegistry(
            $app,
            (array) $app['config']->get('notifications.channels', []),
        ));
    }
}
