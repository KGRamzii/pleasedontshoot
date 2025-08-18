<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Notification;
use App\Services\DiscordService;

class DiscordServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(DiscordService::class, function ($app) {
            return new DiscordService();
        });
    }

    public function boot()
    {
        Notification::extend('discord', function ($app) {
            return $app->make(DiscordService::class);
        });
    }
}
