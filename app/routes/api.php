<?php

use App\Http\Controllers\API\ArticleController;
use App\Http\Controllers\API\CallContentController;
use App\Http\Controllers\API\CustomPageController;
use App\Http\Controllers\API\CustomPageTypeController;
use App\Http\Controllers\API\GalleryCategoryController;
use App\Http\Controllers\API\GalleryImageController;
use App\Http\Controllers\API\QuestionAnswerController;
use App\Http\Controllers\API\ResolveController;
use App\Http\Controllers\API\SiteSettingController;
use Illuminate\Support\Facades\Route;

Route::get('site-setting', [SiteSettingController::class, 'show'])->name('api.site-setting.show');

Route::apiResource('articles', ArticleController::class)->only(['index']);

Route::apiResource('call-contents', CallContentController::class)->only(['index']);

Route::apiResource('question-answers', QuestionAnswerController::class)->only(['index']);

Route::apiResource('gallery-images', GalleryImageController::class)->only(['index']);

Route::apiResource('gallery-categories', GalleryCategoryController::class)->only(['index']);

Route::get('resolve', ResolveController::class)->name('api.resolve');

Route::get('custom-page-types', [CustomPageTypeController::class, 'index'])->name('api.custom-page-types.index');

Route::get('custom-pages/{customPageType:name}', [CustomPageController::class, 'index'])->name('api.custom-pages.index');
