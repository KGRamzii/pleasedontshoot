<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use PDO;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set global PDO options for better performance
        $pdoOptions = [
            PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', true),
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ];

        // Add these options to all database connections
        foreach (config('database.connections') as $connection => $config) {
            config(["database.connections.{$connection}.options" => $pdoOptions]);
        }

        // Listen for query events in development
        if (config('app.debug')) {
            DB::listen(function ($query) {
                // Log slow queries (over 1000ms)
                if ($query->time > 1000) {
                    logger()->warning('Slow query detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time
                    ]);
                }
            });
        }
    }
}
