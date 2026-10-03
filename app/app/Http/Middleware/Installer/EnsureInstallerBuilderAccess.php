<?php

namespace App\Http\Middleware\Installer;

use App\Installer\BuilderAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * インストーラーの中のビルダー(画面と JSON)は、最初の管理者を作ったセッションだけが使える(BuilderAccess)。
 * 使えるときは、このリクエストの間だけその管理者として扱う(操作ログ・版の作成者に残す。セッションにはログインしない)。
 * 使い方: ->middleware('installer.builder')
 */
class EnsureInstallerBuilderAccess
{
    public function __construct(private BuilderAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $administrator = $this->access->administrator($request);

        if ($administrator === null) {
            return $request->expectsJson()
                ? response()->json(['message' => __('このブラウザでは、インストーラーのビルダーを使えません。')], 403)
                : redirect()->route('installer.design');
        }

        Auth::guard('admin')->setUser($administrator);

        return $next($request);
    }
}
