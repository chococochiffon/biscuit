<?php

namespace App\Providers;

use App\Models\Administrator;
use App\View\Composers\CustomPageTypeComposer;
use App\View\Composers\SiteSettingComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        Paginator::useBootstrapFive();

        // カスタムページ管理(種類の管理と種類ごとのページ)はスーパー管理者だけが使える
        Gate::define('manage-custom-pages', fn (Administrator $administrator) => $administrator->isSuperAdmin());

        View::composer('layouts.admin', SiteSettingComposer::class);
        View::composer('layouts.admin', CustomPageTypeComposer::class);
    }
}
