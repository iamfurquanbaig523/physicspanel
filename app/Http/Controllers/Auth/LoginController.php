<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Setting;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Ramsey\Collection\Set;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /* Extended Function from AuthenticatesUsers */
    protected function sendFailedLoginResponse(Request $request)
    {
        $user = User::where('email', $request->get('email'))->withTrashed()->first();
        if (! empty($user->deleted_at)) {
            throw ValidationException::withMessages([
                $this->username() => [trans('auth.user_inactive')],
            ]);
        }

        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);

    }

    /**
     * Set application locale after successful authentication.
     */
    protected function authenticated(Request $request, $user)
    {
        // Prefer app default language stored in settings so admin sees default on login
        // $defaultLanguage = \DB::table('settings')->where('name', 'default_language')->value('value');
        $defaultLanguage = Setting::where('name', 'default_language')->value('value');
        if ($defaultLanguage) {
            // $language = \App\Models\Language::where('code', $defaultLanguage)->first();
            $language = Language::where('code', $defaultLanguage)->first();
            if ($language) {
                \Session::put('locale', $language->code);
                \Session::put('language', (object) $language->toArray());
                app()->setLocale($language->code);
                \Session::save();
            } else {
                \Session::put('locale', $defaultLanguage);
                app()->setLocale($defaultLanguage);
                \Session::save();
            }
        }
    }
}
