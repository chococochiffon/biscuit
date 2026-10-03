<?php

namespace App\Http\Middleware\Installer;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * インストーラーの画面では、セッションとキャッシュを DB からファイルに切り替える(DB ができる前から動かすため)。
 * StartSession より前に置く(インストーラーのミドルウェアのグループ installer の先頭)。
 */
class UseInstallerDrivers
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('session.driver') === 'database') {
            config(['session.driver' => 'file']);
        }

        if (config('cache.default') === 'database') {
            config(['cache.default' => 'file']);
        }

        return $next($request);
    }
}
