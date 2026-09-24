<?php

use App\Http\Controllers\API\ArticleController;
use App\Http\Controllers\API\CallContentController;
use App\Http\Controllers\API\ResolveController;
use App\Http\Controllers\API\SiteSettingController;
use Illuminate\Support\Facades\Route;

Route::get('site-setting', [SiteSettingController::class, 'show'])->name('api.site-setting.show');

Route::apiResource('articles', ArticleController::class)->only(['index']);

Route::apiResource('call-contents', CallContentController::class)->only(['index']);

Route::get('resolve', ResolveController::class)->name('api.resolve');
