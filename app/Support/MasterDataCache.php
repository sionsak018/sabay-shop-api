<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache for read-mostly master data (locations, brands, body types,
 * category attributes). The file cache store has no tag support, so every
 * mutation bumps a single shared version key which abandons all master-data
 * cache entries at once.
 */
class MasterDataCache
{
    private const VERSION_KEY = 'master_data.version';

    public static function remember(string $key, Closure $callback, int $seconds = 86400)
    {
        $version = Cache::get(self::VERSION_KEY, 0);

        return Cache::remember("master_data.{$version}.{$key}", $seconds, $callback);
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, now()->getTimestamp());
    }
}
