<?php

use App\Http\Controllers\API\ArticleController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AuthorController;
use App\Http\Controllers\API\BuilderPreviewController;
use App\Http\Controllers\API\CallContentController;
use App\Http\Controllers\API\CustomPageController;
use App\Http\Controllers\API\CustomPageTypeController;
use App\Http\Controllers\API\GalleryCategoryController;
use App\Http\Controllers\API\GalleryImageController;
use App\Http\Controllers\API\InvitationController;
use App\Http\Controllers\API\LayoutController;
use App\Http\Controllers\API\MeController;
use App\Http\Controllers\API\MyArticleController;
use App\Http\Controllers\API\MyDashboardController;
use App\Http\Controllers\API\MyGalleryImageController;
use App\Http\Controllers\API\MyPageViewController;
use App\Http\Controllers\API\PageViewController;
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

// ページビルダーのプレビュー(管理画面のエディタが発行した、期限付きの署名付き URL でだけ開ける。
// chococo のサーバーは biscuit を別のホスト名で呼ぶことがあるため、署名はパスとクエリだけで確かめる)
Route::get('builder-previews/{pageBuilder}', [BuilderPreviewController::class, 'show'])
    ->middleware('signed:relative')
    ->name('api.builder-previews.show');

Route::get('custom-page-types', [CustomPageTypeController::class, 'index'])->name('api.custom-page-types.index');

Route::get('custom-pages/{customPageType:name}', [CustomPageController::class, 'index'])->name('api.custom-pages.index');

// PV の記録(chococo のサーバーが公開側のページの表示ごとに中継する。共有の鍵が必要で、閲覧者ごとに回数を制限する)
Route::post('page-views', [PageViewController::class, 'store'])
    ->middleware('throttle:page-views')
    ->name('api.page-views.store');

// chococo のマイページ(ログインが必要。Authorization: Bearer で API トークンを送る)
Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:user-login')
    ->name('api.auth.login');
Route::post('auth/login/verify', [AuthController::class, 'verifyCode'])
    ->middleware('throttle:login-code')
    ->name('api.auth.login.verify');
Route::post('auth/login/resend', [AuthController::class, 'resendCode'])
    ->middleware('throttle:login-code')
    ->name('api.auth.login.resend');

// パスワード再設定(ログイン前。メールのリンクは chococo の /reset-password を開く)
Route::post('auth/forgot-password', [PasswordResetController::class, 'forgot'])
    ->middleware('throttle:user-password-reset')
    ->name('api.auth.forgot-password');
Route::post('auth/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:user-password-reset')
    ->name('api.auth.reset-password');

// 管理者からの招待の受諾(ログイン前。リンクの確認と、プロフィール・パスワードの登録)
Route::get('auth/invitation', [InvitationController::class, 'show'])
    ->middleware('throttle:user-invitation')
    ->name('api.auth.invitation.show');
Route::post('auth/invitation', [InvitationController::class, 'accept'])
    ->middleware('throttle:user-invitation')
    ->name('api.auth.invitation.accept');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    Route::get('me', [MeController::class, 'show'])->name('api.me.show');
    Route::put('me/profile', [MeController::class, 'updateProfile'])->name('api.me.profile.update');
    Route::post('me/profile/image', [MeController::class, 'updateImage'])->name('api.me.profile.image');
    Route::put('me/password', [MeController::class, 'updatePassword'])->name('api.me.password.update');

    // 自分の記事のアクセス(マイページのダッシュボード)
    Route::get('me/page-views', [MyPageViewController::class, 'show'])->name('api.me.page-views.show');

    // 自分の記事・ギャラリーの状況・最近の操作・アカウント・画像(マイページのダッシュボード)
    Route::get('me/dashboard', [MyDashboardController::class, 'show'])->name('api.me.dashboard.show');

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

    // マイページからのギャラリーの画像の投稿(画像を受け取る登録・画像の変更はアップロードの回数を制限する)
    Route::apiResource('me/gallery-images', MyGalleryImageController::class)
        ->parameters(['gallery-images' => 'myGalleryImage'])
        ->names('api.me.gallery-images')
        ->middlewareFor('store', 'throttle:user-uploads')
        ->where(['myGalleryImage' => '[0-9]+']);
    Route::post('me/gallery-images/{myGalleryImage}/image', [MyGalleryImageController::class, 'updateImage'])
        ->middleware('throttle:user-uploads')
        ->whereNumber('myGalleryImage')
        ->name('api.me.gallery-images.image');
    Route::post('me/gallery-images/{myGalleryImage}/submit', [MyGalleryImageController::class, 'submit'])
        ->whereNumber('myGalleryImage')
        ->name('api.me.gallery-images.submit');
    Route::post('me/gallery-images/{myGalleryImage}/withdraw', [MyGalleryImageController::class, 'withdraw'])
        ->whereNumber('myGalleryImage')
        ->name('api.me.gallery-images.withdraw');
});
