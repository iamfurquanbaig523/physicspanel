<?php

use App\Http\Controllers\AuthorController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyPageController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;

use App\Http\Controllers\NewsletterSubscriberController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchQueryController;
use App\Http\Controllers\SeoSettingController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShareLinkController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SystemUpdateController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;

Auth::routes();

Route::get('/s/{code}', [ShareLinkController::class, 'redirect'])->name('share-links.redirect');

Route::get('/', static function () {
    if (Auth::user()) {
        return redirect('/home');
    }

    return view('auth.login');
});

Route::group(['prefix' => 'common'], static function () {
    Route::get('/js/lang', [Controller::class, 'readLanguageFile'])->name('common.language.read');
});

Route::group(['middleware' => ['auth', 'language']], static function () {
    Route::group(['prefix' => 'common'], static function () {
        Route::put('/change-row-order', [Controller::class, 'changeRowOrder'])->name('common.row-order.change');
        Route::put('/change-status', [Controller::class, 'changeStatus'])->name('common.status.change');
    });

    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('change-password', [HomeController::class, 'changePasswordIndex'])->name('change-password.index');
    Route::post('change-password', [HomeController::class, 'changePasswordUpdate'])->name('change-password.update');
    Route::get('change-profile', [HomeController::class, 'changeProfileIndex'])->name('change-profile.index');
    Route::post('change-profile', [HomeController::class, 'changeProfileUpdate'])->name('change-profile.update');

    Route::group(['prefix' => 'language'], static function () {
        Route::get('set-language/{lang}', [LanguageController::class, 'setLanguage'])->name('language.set-current');
    });
    Route::post('/language/set-default', [LanguageController::class, 'setDefaultLanguage'])
        ->name('settings.set-default-language');

    Route::group(['prefix' => 'settings'], static function () {
        Route::get('/', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/store', [SettingController::class, 'store'])->name('settings.store');
        Route::get('system', [SettingController::class, 'page'])->name('settings.system');
        Route::get('web-settings', [SettingController::class, 'page'])->name('settings.web-settings');
        Route::get('seo-setting', [SettingController::class, 'page'])->name('settings.seo-settings.index');
        Route::get('error-logs', [LogViewerController::class, 'index'])->name('settings.error-logs.index');
    });

    Route::resource('seo-setting', SeoSettingController::class);

    Route::get('/roles-list', [RoleController::class, 'list'])->name('roles.list');
    Route::resource('roles', RoleController::class);

    Route::group(['prefix' => 'staff'], static function () {
        Route::put('/{id}/change-password', [StaffController::class, 'changePassword'])->name('staff.change-password');
    });
    Route::resource('staff', StaffController::class);

    Route::post('blog/upload-editor-image', [BlogController::class, 'uploadEditorImage'])->name('blog.upload-editor-image');
    Route::resource('blog', BlogController::class);
    Route::get('category/order', [CategoryController::class, 'categoriesReOrder'])->name('category.order');
    Route::get('category/{id}/sub-order', [CategoryController::class, 'subCategoriesReOrder'])->name('sub.category.order.change');
    Route::post('category/order/change', [CategoryController::class, 'updateOrder'])->name('category.order.change');
    Route::resource('category', CategoryController::class);
    Route::resource('authors', AuthorController::class);
    Route::resource('company-pages', CompanyPageController::class);
    Route::resource('newsletter-subscribers', NewsletterSubscriberController::class)->only(['index', 'show', 'destroy']);
    Route::resource('search-queries', SearchQueryController::class)->only(['index', 'show', 'destroy']);

    Route::group(['prefix' => 'contact-us'], static function () {
        Route::get('/', [Controller::class, 'contactUsUIndex'])->name('contact-us.index');
        Route::get('/show', [Controller::class, 'contactUsShow'])->name('contact-us.show');
    });

    Route::group(['prefix' => 'system-update'], static function () {
        Route::get('/', [SystemUpdateController::class, 'index'])->name('system-update.index');
        Route::post('/', [SystemUpdateController::class, 'update'])->name('system-update.update');
    });
    Route::get('reset-purchase-code', [SystemUpdateController::class, 'resetPurchaseCode'])->name('system-update.reset-purchase-code');
});
