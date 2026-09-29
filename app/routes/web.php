<?php

use App\Http\Controllers\AdministratorController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Auth\AdministratorSessionController;
use App\Http\Controllers\ContentModelRelationController;
use App\Http\Controllers\CustomPageEntryController;
use App\Http\Controllers\CustomPageTypeController;
use App\Http\Controllers\GalleryCategoryController;
use App\Http\Controllers\GalleryImageController;
use App\Http\Controllers\LayoutController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\QuestionAnswerController;
use App\Http\Controllers\SinglePageController;
use App\Http\Controllers\SiteSettingController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// biscuit は管理画面と API だけを提供する(公開側サイトは chococo)ため、トップは管理画面へ転送する
// (未ログインなら管理画面の入口からログイン画面へ送られる)
Route::redirect('/', '/admin');

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

// 管理画面(ログインが必要なルート)。独自ルート(tags/search・articles/bulk-approval・single-pages/reorder・gallery-images/reorder・gallery-categories/reorder など)は
// 対応する Route::resource より前に置き、/admin/{administrator} が他の /admin/* を飲み込む管理者の resource は最後に置く
Route::middleware('auth:admin')->group(function () {
    Route::get('admin/tags/search', [TagController::class, 'search'])
        ->name('admin.tags.search');

    Route::post('admin/articles/content-images', [ArticleController::class, 'uploadContentImage'])
        ->name('admin.articles.content-images');

    Route::resource('admin/tags', TagController::class)
        ->names('admin.tags');

    Route::patch('admin/articles/bulk-approval', [ArticleController::class, 'bulkUpdateApproval'])
        ->name('admin.articles.bulk-approval');

    Route::patch('admin/articles/{article}/approval', [ArticleController::class, 'updateApproval'])
        ->name('admin.articles.approval');

    Route::resource('admin/articles', ArticleController::class)
        ->except(['show'])
        ->names('admin.articles');

    Route::resource('admin/site-settings', SiteSettingController::class)
        ->only(['create', 'store', 'show', 'edit', 'update'])
        ->parameters(['site-settings' => 'siteSetting'])
        ->names('admin.site-settings');

    Route::resource('admin/content-model-relations', ContentModelRelationController::class)
        ->parameters(['content-model-relations' => 'contentModelRelation'])
        ->names('admin.content-model-relations');

    Route::resource('admin/users', UserController::class)
        ->names('admin.users');

    Route::patch('admin/single-pages/reorder', [SinglePageController::class, 'reorder'])
        ->name('admin.single-pages.reorder');

    Route::resource('admin/single-pages', SinglePageController::class)
        ->parameters(['single-pages' => 'singlePage'])
        ->except(['show'])
        ->names('admin.single-pages');

    Route::patch('admin/gallery-categories/reorder', [GalleryCategoryController::class, 'reorder'])
        ->name('admin.gallery-categories.reorder');

    Route::resource('admin/gallery-categories', GalleryCategoryController::class)
        ->parameters(['gallery-categories' => 'galleryCategory'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('admin.gallery-categories');

    Route::patch('admin/gallery-images/reorder', [GalleryImageController::class, 'reorder'])
        ->name('admin.gallery-images.reorder');

    Route::resource('admin/gallery-images', GalleryImageController::class)
        ->parameters(['gallery-images' => 'galleryImage'])
        ->except(['show'])
        ->names('admin.gallery-images');

    // カスタムページ管理はスーパー管理者だけが使える(AppServiceProvider の manage-custom-pages)
    Route::middleware('can:manage-custom-pages')->group(function () {
        Route::resource('admin/custom-page-types', CustomPageTypeController::class)
            ->parameters(['custom-page-types' => 'customPageType'])
            ->except(['show'])
            ->names('admin.custom-page-types');

        Route::resource('admin/custom-pages/{customPageType}/entries', CustomPageEntryController::class)
            ->parameters(['entries' => 'entry'])
            ->except(['show'])
            ->whereNumber('entry')
            ->names('admin.custom-pages.entries');
    });

    // レイアウト管理は 1 画面で編集・保存する(一覧・登録画面は持たない)
    Route::get('admin/layouts', [LayoutController::class, 'edit'])
        ->name('admin.layouts.edit');

    Route::put('admin/layouts', [LayoutController::class, 'update'])
        ->name('admin.layouts.update');

    Route::resource('admin/question-answers', QuestionAnswerController::class)
        ->parameters(['question-answers' => 'questionAnswer'])
        ->names('admin.question-answers');

    Route::resource('admin', AdministratorController::class)
        ->parameters(['admin' => 'administrator']);
});
