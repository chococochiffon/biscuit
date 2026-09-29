<?php

use App\Http\Controllers\API\ArticleController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AuthorController;
use App\Http\Controllers\API\CallContentController;
use App\Http\Controllers\API\CustomPageController;
use App\Http\Controllers\API\CustomPageTypeController;
use App\Http\Controllers\API\GalleryCategoryController;
use App\Http\Controllers\API\GalleryImageController;
use App\Http\Controllers\API\LayoutController;
use App\Http\Controllers\API\MeController;
use App\Http\Controllers\API\MyArticleController;
use App\Http\Controllers\API\PasswordResetController;
use App\Http\Controllers\API\QuestionAnswerController;
use App\Http\Controllers\API\ResolveController;
use App\Http\Controllers\API\SiteSettingController;
use Illuminate\Support\Facades\Route;

Route::get('site-setting', [SiteSettingController::class, 'show'])->name('api.site-setting.show');

Route::get('layout', [LayoutController::class, 'show'])->name('api.layout.show');

Route::apiResource('articles', ArticleController::class)->only(['index']);

Route::get('authors/{id}', [AuthorController::class, 'show'])->whereNumber('id')->name('api.authors.show');

Route::apiResource('call-contents', CallContentController::class)->only(['index']);

Route::apiResource('question-answers', QuestionAnswerController::class)->only(['index']);

Route::apiResource('gallery-images', GalleryImageController::class)->only(['index']);

Route::apiResource('gallery-categories', GalleryCategoryController::class)->only(['index']);

Route::get('resolve', ResolveController::class)->name('api.resolve');

Route::get('custom-page-types', [CustomPageTypeController::class, 'index'])->name('api.custom-page-types.index');

Route::get('custom-pages/{customPageType:name}', [CustomPageController::class, 'index'])->name('api.custom-pages.index');

// chococo のマイページ(ログインが必要。Authorization: Bearer で API トークンを送る)
Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:user-login')
    ->name('api.auth.login');

// パスワード再設定(ログイン前。メールのリンクは chococo の /reset-password を開く)
Route::post('auth/forgot-password', [PasswordResetController::class, 'forgot'])
    ->middleware('throttle:user-password-reset')
    ->name('api.auth.forgot-password');
Route::post('auth/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:user-password-reset')
    ->name('api.auth.reset-password');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    Route::get('me', [MeController::class, 'show'])->name('api.me.show');
    Route::put('me/profile', [MeController::class, 'updateProfile'])->name('api.me.profile.update');
    Route::post('me/profile/image', [MeController::class, 'updateImage'])->name('api.me.profile.image');
    Route::put('me/password', [MeController::class, 'updatePassword'])->name('api.me.password.update');

    Route::get('me/article-paths', [MyArticleController::class, 'pathOptions'])->name('api.me.article-paths.index');
    Route::get('me/tags', [MyArticleController::class, 'searchTags'])->name('api.me.tags.search');
    Route::post('me/articles/content-images', [MyArticleController::class, 'uploadContentImage'])
        ->middleware('throttle:user-uploads')
        ->name('api.me.articles.content-images');
    Route::apiResource('me/articles', MyArticleController::class)
        ->parameters(['articles' => 'myArticle'])
        ->names('api.me.articles')
        ->where(['myArticle' => '[0-9]+']);
    Route::post('me/articles/{myArticle}/thumbnail', [MyArticleController::class, 'updateThumbnail'])
        ->middleware('throttle:user-uploads')
        ->whereNumber('myArticle')
        ->name('api.me.articles.thumbnail');
    Route::post('me/articles/{myArticle}/submit', [MyArticleController::class, 'submit'])
        ->whereNumber('myArticle')
        ->name('api.me.articles.submit');
    Route::post('me/articles/{myArticle}/withdraw', [MyArticleController::class, 'withdraw'])
        ->whereNumber('myArticle')
        ->name('api.me.articles.withdraw');
});
