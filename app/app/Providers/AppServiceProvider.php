<?php

namespace App\Providers;

use App\Models\Administrator;
use App\Models\Article;
use App\View\Composers\CustomPageTypeComposer;
use App\View\Composers\PendingArticleComposer;
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

        // chococo のマイページからの画像のアップロード(記事の本文・サムネイル)は、ユーザーごとに回数を制限する
        RateLimiter::for('user-uploads', function (Request $request) {
            return Limit::perMinute(30)->by((string) $request->user()?->getAuthIdentifier());
        });

        // マイページの記事({myArticle})は、ログイン中のユーザーの記事だけを取り出す(ほかのユーザーの記事・論理削除した記事は 404)。
        // ルートのキャッシュ時は routes/api.php が読まれないため、ここで登録する
        Route::bind('myArticle', fn (string $value): Article => Auth::user()?->articles()->whereKey($value)->firstOrFail() ?? abort(404));

        Paginator::useBootstrapFive();

        // カスタムページ管理(種類の管理と種類ごとのページ)はスーパー管理者だけが使える
        Gate::define('manage-custom-pages', fn (Administrator $administrator) => $administrator->isSuperAdmin());

        // 操作ログ(監査ログ)は、ほかの管理者の操作や IP アドレスを含むためスーパー管理者だけが見られる
        Gate::define('view-audit-logs', fn (Administrator $administrator) => $administrator->isSuperAdmin());

        View::composer('layouts.admin', SiteSettingComposer::class);
        View::composer('layouts.admin', CustomPageTypeComposer::class);
        View::composer(['layouts.admin', 'admin.articles.index'], PendingArticleComposer::class);
    }
}
