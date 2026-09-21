<?php

use App\Http\Controllers\AdministratorController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Auth\AdministratorSessionController;
use App\Http\Controllers\ContentModelRelationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SinglePageController;
use App\Http\Controllers\SiteSettingController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('locale/{locale}', LocaleController::class)->name('locale.update');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AdministratorSessionController::class, 'create'])->name('login');
        Route::post('login', [AdministratorSessionController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
    });

    Route::post('logout', [AdministratorSessionController::class, 'destroy'])
        ->middleware('auth:admin')
        ->name('logout');
});

Route::get('admin/tags/search', [TagController::class, 'search'])
    ->name('admin.tags.search')
    ->middleware('auth:admin');

Route::post('admin/articles/content-images', [ArticleController::class, 'uploadContentImage'])
    ->name('admin.articles.content-images')
    ->middleware('auth:admin');

Route::resource('admin/tags', TagController::class)
    ->names('admin.tags')
    ->middleware('auth:admin');

Route::resource('admin/articles', ArticleController::class)
    ->names('admin.articles')
    ->middleware('auth:admin');

Route::resource('admin/site-settings', SiteSettingController::class)
    ->only(['create', 'store', 'show', 'edit', 'update'])
    ->parameters(['site-settings' => 'siteSetting'])
    ->names('admin.site-settings')
    ->middleware('auth:admin');

Route::resource('admin/content-model-relations', ContentModelRelationController::class)
    ->parameters(['content-model-relations' => 'contentModelRelation'])
    ->names('admin.content-model-relations')
    ->middleware('auth:admin');

Route::resource('admin/users', UserController::class)
    ->names('admin.users')
    ->middleware('auth:admin');

Route::resource('admin/single-pages', SinglePageController::class)
    ->parameters(['single-pages' => 'singlePage'])
    ->names('admin.single-pages')
    ->middleware('auth:admin');

Route::resource('admin', AdministratorController::class)
    ->parameters(['admin' => 'administrator'])
    ->middleware('auth:admin');
