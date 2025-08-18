<?php

namespace App\Providers;

use App\Models\Team;
use App\Policies\TeamPolicy;
use App\Services\DiscordService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    protected $policies = [
        Team::class => TeamPolicy::class,
    ];
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DiscordService::class, function ($app) {
            return new DiscordService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('removeMember', [TeamPolicy::class, 'removeMember']);

        // Register Discord notification channel
        Notification::extend('discord', function ($app) {
            return $app->make(\App\Notifications\Channels\DiscordChannel::class);
        });
    }
}
