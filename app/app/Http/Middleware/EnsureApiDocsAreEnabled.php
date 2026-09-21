<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiDocsAreEnabled
{
    /**
     * Handle an incoming request.
     *
     * 本番環境ではAPIドキュメント(Swagger UI)へのアクセスを拒否する。
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(app()->isProduction(), 404);

        return $next($request);
    }
}
