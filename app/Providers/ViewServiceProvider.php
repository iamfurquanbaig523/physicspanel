<?php

namespace App\Providers;

use App\Models\Language;
use App\Models\Setting;
use App\Services\CachingService;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        /*** Header File ***/
        // View::composer('layouts.topbar', static function ($view) {
        //     $languages = CachingService::getLanguages();
        //     // $defaultLanguage = CachingService::getDefaultLanguage();
        //     $settings = CachingService::getSystemSettings();

        //     // $currentLanguageCode = ::get('language');
        //     $defaultLanguage = Setting::where('name', 'default_language')->first();

        //     // $currentLanguageCode = Setting::where('name', 'default_language')->first();

        //     $currentLanguage = Language::where('code', Session::get('locale'))->first();

        //     Session::put('language', $defaultLanguage);

        //     $view->with([
        //         'languages' => $languages,
        //         'defaultLanguage' => $defaultLanguage,
        //         'currentLanguage' => $currentLanguage,
        //         'settings' => $settings
        //     ]);
        // });

      View::composer('layouts.topbar', function ($view) {
            $languages = CachingService::getLanguages();
            
            // Always get the most recent default from DB
            $defaultLangCode = Setting::where('name', 'default_language')->value('value') ?? 'en';
            $defaultLanguage = $languages->where('code', $defaultLangCode)->first();

            // If session is empty, use the database default
            $currentLocale = Session::get('locale', $defaultLangCode);
            $currentLanguage = $languages->where('code', $currentLocale)->first();

            $view->with([
                'languages'       => $languages,
                'defaultLanguage' => $defaultLanguage, // Now correctly shows the DB value
                'currentLanguage' => $currentLanguage,
                'settings'        => CachingService::getSystemSettings()
            ]);
        });




        View::composer('layouts.sidebar', static function (\Illuminate\View\View $view) {
            $settings = CachingService::getSystemSettings('company_logo');
            $view->with('company_logo', $settings ?? '');
        });

        View::composer('layouts.main', static function (\Illuminate\View\View $view) {
            $settings = CachingService::getSystemSettings('favicon_icon');
            $view->with('favicon', $settings ?? '');
            $view->with('lang', Session::get('language'));
        });

        View::composer('auth.login', static function (\Illuminate\View\View $view) {
            $favicon_icon = CachingService::getSystemSettings('favicon_icon');
            $company_logo = CachingService::getSystemSettings('company_logo');
            $login_image = CachingService::getSystemSettings('login_image');
            $view->with('company_logo', $company_logo ?? '');
            $view->with('favicon', $favicon_icon ?? '');
            $view->with('login_bg_image', $login_image ?? '');
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
