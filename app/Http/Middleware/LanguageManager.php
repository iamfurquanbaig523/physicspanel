<?php

namespace App\Http\Middleware;

use App\Models\Language;
use App\Models\Setting;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class LanguageManager
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return Response|RedirectResponse
     */

     public function handle(Request $request, Closure $next)
    {
        // 1. Set a hardcoded fallback first
        $locale = config('app.fallback_locale', 'en');
    
        try {
            // Only attempt DB logic if we aren't running a migration/install command
            if (!app()->runningInConsole()) {
                
                // Check for the settings table
                if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                    $globalDefault = Cache::rememberForever('global_default_language', function () {
                        return Setting::where('name', 'default_language')->value('value') ?? 'en';
                    });
    
                    $locale = Session::get('locale', $globalDefault);
                    Session::put('locale', $locale);
                }
    
                // Check for the languages table
                if (\Illuminate\Support\Facades\Schema::hasTable('languages')) {
                    $language = \App\Models\Language::where('code', $locale)->first();
                    if ($language) {
                        Session::put('language', $language);
                        Session::put('is_rtl', (bool)$language->rtl);
                    }
                }
            }
        } catch (\Exception $e) {
            // If DB connection fails (Access Denied), we catch it here.
            // The app will continue using the default $locale defined above.
        }
    
        app()->setLocale($locale);
    
        return $next($request);
    }
}
