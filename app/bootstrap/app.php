<?php

use App\Http\Middleware\Installer\EnsureInstallerBuilderAccess;
use App\Http\Middleware\Installer\EnsureInstallerStep;
use App\Http\Middleware\Installer\EnsureNotInstalled;
use App\Http\Middleware\Installer\RedirectIfNotInstalled;
use App\Http\Middleware\Installer\UseInstallerDrivers;
use App\Http\Middleware\SetLocale;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // インストーラー(/install)は DB を使わないミドルウェアのグループ installer で動かす
        then: fn () => Route::middleware('installer')->group(base_path('routes/installer.php')),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class]);

        // インストールを終えるまでは、管理画面・API をインストーラーへ回す(DB を使うセッションなどより前に置く)
        $middleware->web(prepend: [RedirectIfNotInstalled::class]);
        $middleware->api(prepend: [RedirectIfNotInstalled::class]);

        // インストーラーのグループ: セッション・キャッシュをファイルにしてから(UseInstallerDrivers)セッションを始め、インストール済みなら入れない
        $middleware->group('installer', [
            UseInstallerDrivers::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            SubstituteBindings::class,
            EnsureNotInstalled::class,
        ]);
        $middleware->alias(['installer.step' => EnsureInstallerStep::class, 'installer.builder' => EnsureInstallerBuilderAccess::class]);

        // ページビルダーの内容(JSON。下書き・テンプレート)は送られたとおりに検証・保存する(空文字を null にしたり、前後の空白を削ったりしない)
        $isPageBuilderJson = fn (Request $request) => $request->is('admin/json/builder/*', 'admin/json/builder-templates', 'admin/json/builder-templates/*', 'install/design/builder/json', 'install/design/builder/json/*');
        $middleware->convertEmptyStringsToNull(except: [$isPageBuilderJson]);
        $middleware->trimStrings(except: [$isPageBuilderJson]);

        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin*') ? route('admin.login') : null,
        );

        // ログイン済みでログイン画面(guest:admin)を開いたときはダッシュボードへ(トップ / は 404 のため使わない)
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
