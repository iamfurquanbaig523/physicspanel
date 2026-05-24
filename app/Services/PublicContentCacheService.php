<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class PublicContentCacheService
{
    private const VERSION_KEY = 'public_content_cache_version';
    private const CACHE_PREFIX = 'public_content';

    public static function remember(string $key, callable $callback, int $seconds = 300): mixed
    {
        $version = (int) Cache::rememberForever(self::VERSION_KEY, static fn () => 1);
        $cacheKey = self::CACHE_PREFIX.':'.$version.':'.sha1($key);

        return Cache::remember($cacheKey, $seconds, $callback);
    }

    public static function invalidate(?array $paths = null): void
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);
        Cache::forever(self::VERSION_KEY, $version + 1);

        FrontendRevalidationService::revalidate($paths);
    }

    public static function cacheControl(int $seconds = 300): string
    {
        return 'public, max-age=0, s-maxage='.$seconds.', stale-while-revalidate=86400';
    }
}
