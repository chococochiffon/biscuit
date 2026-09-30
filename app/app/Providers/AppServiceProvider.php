<?php

namespace App\Providers;

use App\Models\Administrator;
use App\Models\Article;
use App\Models\GalleryImage;
use App\View\Composers\CustomPageTypeComposer;
use App\View\Composers\PendingArticleComposer;
use App\View\Composers\PendingGalleryImageComposer;
use App\View\Composers\SiteSettingComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower($request->string('email')).'|'.$request->ip()
            ));
        });

        // chococo のマイページのログイン(API トークンの発行)も、管理画面と同じくメールアドレスと接続元ごとに制限する
        RateLimiter::for('user-login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower($request->string('email')).'|'.$request->ip()
            ));
        });

        // パスワード再設定(メールの送信・再設定)も、メールアドレスと接続元ごとに制限する
        RateLimiter::for('user-password-reset', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower($request->string('email')).'|'.$request->ip()
            ));
        });

        // 招待の受諾(リンクの確認・登録)は、トークンを総当たりされないよう接続元ごとに制限する
        RateLimiter::for('user-invitation', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        // chococo のマイページからの画像のアップロード(記事の本文・サムネイル、ギャラリーの画像)は、ユーザーごとに回数を制限する
        RateLimiter::for('user-uploads', function (Request $request) {
            return Limit::perMinute(30)->by((string) $request->user()?->getAuthIdentifier());
        });

        // マイページの記事({myArticle})・ギャラリーの画像({myGalleryImage})は、ログイン中のユーザーのものだけを取り出す
        // (ほかのユーザーのもの・論理削除したものは 404)。
        // ルートのキャッシュ時は routes/api.php が読まれないため、ここで登録する
        Route::bind('myArticle', fn (string $value): Article => Auth::user()?->articles()->whereKey($value)->firstOrFail() ?? abort(404));
        Route::bind('myGalleryImage', fn (string $value): GalleryImage => Auth::user()?->galleryImages()->whereKey($value)->firstOrFail() ?? abort(404));

        Paginator::useBootstrapFive();

        // カスタムページ管理(種類の管理と種類ごとのページ)はスーパー管理者だけが使える
        Gate::define('manage-custom-pages', fn (Administrator $administrator) => $administrator->isSuperAdmin());

        // 操作ログ(監査ログ)は、ほかの管理者の操作や IP アドレスを含むためスーパー管理者だけが見られる
        Gate::define('view-audit-logs', fn (Administrator $administrator) => $administrator->isSuperAdmin());

        View::composer('layouts.admin', SiteSettingComposer::class);
        View::composer('layouts.admin', CustomPageTypeComposer::class);
        View::composer(['layouts.admin', 'admin.articles.index'], PendingArticleComposer::class);
        View::composer(['layouts.admin', 'admin.gallery_images.index'], PendingGalleryImageComposer::class);
    }
}
