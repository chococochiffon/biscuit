<?php

namespace App\Http\Middleware;

use App\Support\AdminListUrl;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RememberAdminListUrl
{
    /**
     * 管理画面の一覧の画面を開いたとき、そのクエリ付きの URL を覚える(更新・削除などのあとに同じページへ戻すため)。
     * JSON を返す一覧(管理モーダルが使うもの)は覚えない。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET')
            && $request->hasSession()
            && AdminListUrl::isListRoute($request->route()?->getName())
            && $response->isSuccessful()
            && ! $response instanceof JsonResponse) {
            AdminListUrl::remember($request->path(), $request->fullUrl());
        }

        return $response;
    }
}
