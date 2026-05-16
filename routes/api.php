<?php

use App\Http\Controllers\Content\PublicContentController;
use Illuminate\Support\Facades\Route;

Route::prefix('site')->group(function () {
    Route::get('settings', [PublicContentController::class, 'settings']);
    Route::get('blogs', [PublicContentController::class, 'blogs']);
    Route::get('blogs/{slug}', [PublicContentController::class, 'blog']);
    Route::get('categories', [PublicContentController::class, 'categories']);
    Route::get('categories/{slug}', [PublicContentController::class, 'category']);
    Route::get('articles', [PublicContentController::class, 'articles']);
    Route::get('authors', [PublicContentController::class, 'authors']);
    Route::get('authors/{slug}', [PublicContentController::class, 'author']);
    Route::get('company-pages/{slug}', [PublicContentController::class, 'companyPage']);
    Route::post('contact', [PublicContentController::class, 'storeContact']);
    Route::post('newsletter', [PublicContentController::class, 'storeNewsletter']);
    Route::post('search-query', [PublicContentController::class, 'storeSearchQuery']);
    Route::get('search', [PublicContentController::class, 'search']);
});
