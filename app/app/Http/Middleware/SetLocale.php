<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * セッションに保存された言語設定をアプリケーションに反映する。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (is_string($locale) && in_array($locale, config('app.available_locales'), true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
