<?php

use App\Http\Controllers\LanguageController;
use App\Models\Language;
use Illuminate\Support\Facades\Session;
use App\Models\Setting;

function current_language()
{
    // if (Session::get('language') == 'en' || Session::get('language') == 'fr') {
    //     $lang = Session::get('language');
    //     Session::put('language', $lang);
    //     Session::put('locale', $lang);
    //     app()->setLocale(Session::get('locale'));
    // } else {
    //     $lang = 'en';
    //     Session::put('language', $lang);
    //     Session::put('locale', $lang);
    //     app()->setLocale(Session::get('locale'));
    // }
    $lang = Session::get('locale');
    app()->setLocale($lang);
}

function get_language()
{
    return Language::get();
}
