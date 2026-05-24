<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FrontendRevalidationService
{
    public static function revalidate(?array $paths = null): void
    {
        $url = config('services.frontend.revalidate_url');
        $secret = config('services.frontend.revalidate_secret');

        if (empty($url) || empty($secret)) {
            return;
        }

        try {
            Http::timeout(2)
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'secret' => $secret,
                    'paths' => $paths ?: ['/', '/blog', '/series'],
                ]);
        } catch (Throwable $th) {
            Log::warning('Frontend revalidation failed', [
                'message' => $th->getMessage(),
            ]);
        }
    }
}
