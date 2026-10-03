<?php

namespace App\Http\Middleware\Installer;

use App\Installer\InstallerManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * インストールを終えるまでは、管理画面・API をインストーラーへ回す(API は 503)。
 * DB を使うミドルウェア(セッションなど)より前に置く(web・api のグループの先頭)。
 */
class RedirectIfNotInstalled
{
    public function __construct(private InstallerManager $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installer->isInstalled()) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => __('Biscuit のインストールが済んでいません。')], 503);
        }

        return redirect()->route('installer.requirements');
    }
}
