<?php

use App\Http\Controllers\AdministratorController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticlePathOptionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AdministratorSessionController;
use App\Http\Controllers\ContentModelRelationController;
use App\Http\Controllers\ContentModelRelationJsonController;
use App\Http\Controllers\CustomPageEntryController;
use App\Http\Controllers\CustomPageTypeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GalleryCategoryController;
use App\Http\Controllers\GalleryImageController;
use App\Http\Controllers\LayoutController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PageBuilderComponentController;
use App\Http\Controllers\PageBuilderComponentJsonController;
use App\Http\Controllers\PageBuilderController;
use App\Http\Controllers\PageBuilderJsonController;
use App\Http\Controllers\PageBuilderTemplateJsonController;
use App\Http\Controllers\PageBuilderTransferJsonController;
use App\Http\Controllers\PageViewController;
use App\Http\Controllers\QuestionAnswerController;
use App\Http\Controllers\SinglePageController;
use App\Http\Controllers\SiteSettingController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TagJsonController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserInvitationController;
use Illuminate\Support\Facades\Route;

// biscuit は管理画面と API だけを提供する(公開側サイトは chococo)。トップ(/)は管理画面の場所を知らせないよう、
// ルートを定義せず 404 にする(/index など存在しない URL と同じ)

Route::get('locale/{locale}', LocaleController::class)->name('locale.update');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AdministratorSessionController::class, 'create'])->name('login');
        Route::post('login', [AdministratorSessionController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');

        // 二段階認証: メールで送った確認コードの入力と再送
        Route::get('login/verify', [AdministratorSessionController::class, 'verifyForm'])->name('login.verify');
        Route::post('login/verify', [AdministratorSessionController::class, 'verify'])
            ->middleware('throttle:login-code')
            ->name('login.verify.store');
        Route::post('login/resend', [AdministratorSessionController::class, 'resend'])
            ->middleware('throttle:login-code')
            ->name('login.resend');
    });

    Route::post('logout', [AdministratorSessionController::class, 'destroy'])
        ->middleware('auth:admin')
        ->name('logout');
});

// 管理画面(ログインが必要なルート)。独自ルート(articles/bulk-approval・single-pages/reorder・gallery-images/reorder・gallery-categories/reorder など)は
// 対応する Route::resource より前に置き、/admin/{administrator} が他の /admin/* を飲み込む管理者の resource は最後に置く
Route::middleware('auth:admin')->group(function () {
    // ダッシュボード(ログイン後の既定の画面)
    Route::get('admin/dashboard', DashboardController::class)
        ->name('admin.dashboard');

    // アクセス解析(公開側の PV・UU の集計。閲覧だけ)
    Route::get('admin/page-views', [PageViewController::class, 'index'])
        ->name('admin.page-views.index');

    // 管理モーダル・タグ選択が Ajax で使う JSON(画面用のコントローラーとは分ける)
    Route::get('admin/json/tags/search', [TagJsonController::class, 'search'])
        ->name('admin.json.tags.search');

    Route::resource('admin/json/tags', TagJsonController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('admin.json.tags');

    Route::resource('admin/json/content-model-relations', ContentModelRelationJsonController::class)
        ->parameters(['content-model-relations' => 'contentModelRelation'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('admin.json.content-model-relations');

    // ページビルダーのエディタの画面。対象はトップと固定ページ
    Route::get('admin/builder/top', [PageBuilderController::class, 'edit'])
        ->name('admin.builder.top');
    Route::get('admin/builder/single-pages/{singlePage}', [PageBuilderController::class, 'edit'])
        ->name('admin.builder.single-pages');
    Route::get('admin/builder/components/{pageBuilderComponent}', [PageBuilderController::class, 'editComponent'])
        ->name('admin.builder.components');

    // グローバルコンポーネント(一覧・登録・名前の変更・削除。中身はエディタで編集する)
    Route::resource('admin/builder-components', PageBuilderComponentController::class)
        ->parameters(['builder-components' => 'pageBuilderComponent'])
        ->except(['show'])
        ->names('admin.builder-components');

    // グローバルコンポーネントの JSON(一覧はページのエディタのブロックの選択肢と見本、ほかはコンポーネントのエディタが使う)
    Route::get('admin/json/builder-components', [PageBuilderComponentJsonController::class, 'index'])
        ->name('admin.json.builder-components.index');
    Route::get('admin/json/builder/components/{pageBuilderComponent}', [PageBuilderComponentJsonController::class, 'show'])
        ->name('admin.json.builder.components.show');
    Route::put('admin/json/builder/components/{pageBuilderComponent}', [PageBuilderComponentJsonController::class, 'update'])
        ->name('admin.json.builder.components.update');
    Route::post('admin/json/builder/components/{pageBuilderComponent}/publish', [PageBuilderComponentJsonController::class, 'publish'])
        ->name('admin.json.builder.components.publish');
    Route::post('admin/json/builder/components/{pageBuilderComponent}/discard', [PageBuilderComponentJsonController::class, 'discard'])
        ->name('admin.json.builder.components.discard');
    Route::get('admin/json/builder/components/{pageBuilderComponent}/versions', [PageBuilderComponentJsonController::class, 'versions'])
        ->name('admin.json.builder.components.versions.index');
    Route::get('admin/json/builder/components/{pageBuilderComponent}/versions/{pageBuilderVersion}', [PageBuilderComponentJsonController::class, 'version'])
        ->name('admin.json.builder.components.versions.show');

    // ページビルダーのテンプレート(エディタが一覧・保存・削除に使う JSON)
    Route::resource('admin/json/builder-templates', PageBuilderTemplateJsonController::class)
        ->parameters(['builder-templates' => 'pageBuilderTemplate'])
        ->only(['index', 'store', 'destroy'])
        ->names('admin.json.builder-templates');

    // ページビルダーのエディタが使う JSON。対象はトップ(admin/json/builder/top)と固定ページ(admin/json/builder/single-pages/{singlePage})
    Route::post('admin/json/builder/images', [PageBuilderJsonController::class, 'storeImage'])
        ->name('admin.json.builder.images');
    Route::post('admin/json/builder/export', [PageBuilderTransferJsonController::class, 'export'])
        ->name('admin.json.builder.export');
    Route::post('admin/json/builder/import', [PageBuilderTransferJsonController::class, 'import'])
        ->name('admin.json.builder.import');
    Route::get('admin/json/builder/article-list', [PageBuilderJsonController::class, 'articleList'])
        ->name('admin.json.builder.article-list');
    Route::get('admin/json/builder/navigation', [PageBuilderJsonController::class, 'navigation'])
        ->name('admin.json.builder.navigation');
    Route::get('admin/json/builder/gallery', [PageBuilderJsonController::class, 'gallery'])
        ->name('admin.json.builder.gallery');

    Route::get('admin/json/builder/top', [PageBuilderJsonController::class, 'show'])
        ->name('admin.json.builder.top.show');
    Route::put('admin/json/builder/top', [PageBuilderJsonController::class, 'update'])
        ->name('admin.json.builder.top.update');
    Route::post('admin/json/builder/top/publish', [PageBuilderJsonController::class, 'publish'])
        ->name('admin.json.builder.top.publish');
    Route::post('admin/json/builder/top/discard', [PageBuilderJsonController::class, 'discard'])
        ->name('admin.json.builder.top.discard');
    Route::get('admin/json/builder/top/preview-url', [PageBuilderJsonController::class, 'previewUrl'])
        ->name('admin.json.builder.top.preview-url');
    Route::get('admin/json/builder/top/versions', [PageBuilderJsonController::class, 'versions'])
        ->name('admin.json.builder.top.versions.index');
    Route::get('admin/json/builder/top/versions/{pageBuilderVersion}', [PageBuilderJsonController::class, 'topVersion'])
        ->name('admin.json.builder.top.versions.show');

    Route::get('admin/json/builder/single-pages/{singlePage}', [PageBuilderJsonController::class, 'show'])
        ->name('admin.json.builder.single-pages.show');
    Route::put('admin/json/builder/single-pages/{singlePage}', [PageBuilderJsonController::class, 'update'])
        ->name('admin.json.builder.single-pages.update');
    Route::post('admin/json/builder/single-pages/{singlePage}/publish', [PageBuilderJsonController::class, 'publish'])
        ->name('admin.json.builder.single-pages.publish');
    Route::post('admin/json/builder/single-pages/{singlePage}/discard', [PageBuilderJsonController::class, 'discard'])
        ->name('admin.json.builder.single-pages.discard');
    Route::get('admin/json/builder/single-pages/{singlePage}/preview-url', [PageBuilderJsonController::class, 'previewUrl'])
        ->name('admin.json.builder.single-pages.preview-url');
    Route::get('admin/json/builder/single-pages/{singlePage}/versions', [PageBuilderJsonController::class, 'versions'])
        ->name('admin.json.builder.single-pages.versions.index');
    Route::get('admin/json/builder/single-pages/{singlePage}/versions/{pageBuilderVersion}', [PageBuilderJsonController::class, 'singlePageVersion'])
        ->name('admin.json.builder.single-pages.versions.show');

    Route::post('admin/articles/content-images', [ArticleController::class, 'uploadContentImage'])
        ->name('admin.articles.content-images');

    Route::resource('admin/tags', TagController::class)
        ->names('admin.tags');

    Route::patch('admin/articles/bulk-approval', [ArticleController::class, 'bulkUpdateApproval'])
        ->name('admin.articles.bulk-approval');

    Route::patch('admin/articles/{article}/approval', [ArticleController::class, 'updateApproval'])
        ->name('admin.articles.approval');

    Route::patch('admin/article-path-options/reorder', [ArticlePathOptionController::class, 'reorder'])
        ->name('admin.article-path-options.reorder');

    Route::resource('admin/article-path-options', ArticlePathOptionController::class)
        ->parameters(['article-path-options' => 'articlePathOption'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('admin.article-path-options');

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

    // ユーザーの招待(メールアドレスを入れて招待のメールを送る)と、まだ招待を受けていないユーザーへの再送
    Route::get('admin/users/invite', [UserInvitationController::class, 'create'])
        ->name('admin.users.invite');

    Route::post('admin/users/invite', [UserInvitationController::class, 'store'])
        ->name('admin.users.invite.store');

    Route::post('admin/users/{user}/invitation', [UserInvitationController::class, 'resend'])
        ->name('admin.users.invitation.resend');

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

    // 操作ログ(監査ログ)は閲覧だけで、スーパー管理者だけが見られる(AppServiceProvider の view-audit-logs)
    Route::middleware('can:view-audit-logs')->group(function () {
        Route::resource('admin/audit-logs', AuditLogController::class)
            ->parameters(['audit-logs' => 'auditLog'])
            ->only(['index', 'show'])
            ->names('admin.audit-logs');
    });

    Route::resource('admin/question-answers', QuestionAnswerController::class)
        ->parameters(['question-answers' => 'questionAnswer'])
        ->names('admin.question-answers');

    Route::resource('admin', AdministratorController::class)
        ->parameters(['admin' => 'administrator']);
});
