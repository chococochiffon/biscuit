<?php

use App\Http\Controllers\API\ArticleController;
use App\Http\Controllers\API\SinglePageController;
use App\Http\Controllers\API\SiteSettingController;
use Illuminate\Support\Facades\Route;

Route::get('site-setting', [SiteSettingController::class, 'show'])->name('api.site-setting.show');

Route::apiResource('articles', ArticleController::class)->only(['index', 'show']);

Route::apiResource('single-pages', SinglePageController::class)
    ->parameters(['single-pages' => 'singlePage'])
    ->only(['index', 'show']);
