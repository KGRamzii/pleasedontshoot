<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait CacheHelper
{
    /**
     * Get cached data or store new data in cache
     */
    protected function cacheData(string $key, int $seconds, callable $callback)
    {
        // Use file driver by default, falls back to database if file system isn't writable
        return Cache::driver('file')->remember($key, $seconds, $callback);
    }

    /**
     * Clear cached data
     */
    protected function clearCache(string $key): void
    {
        Cache::driver('file')->forget($key);
    }

    /**
     * Get model-specific cache key
     */
    protected function getCacheKey(string $type): string
    {
        return strtolower(class_basename($this)) . ".{$this->id}.{$type}";
    }
}
