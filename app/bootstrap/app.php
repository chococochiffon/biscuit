<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class]);

        // ページビルダーの内容(JSON)は送られたとおりに検証・保存する(空文字を null にしたり、前後の空白を削ったりしない)
        $isPageBuilderJson = fn (Request $request) => $request->is('admin/json/builder/*');
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
