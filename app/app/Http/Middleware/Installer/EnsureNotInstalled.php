<?php

namespace App\Http\Middleware\Installer;

use App\Installer\InstallerManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * インストール済みなら、インストーラーの画面・API には入れない(管理画面のログインへ回す。JSON は 404)。
 * URL を知っているだけで、DB の設定の変更・管理者の作り直し・マイグレーションのやり直しができないようにする。
 */
class EnsureNotInstalled
{
    public function __construct(private InstallerManager $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installer->isInstalled()) {
            return $request->expectsJson() ? response()->json(['message' => 'Not Found'], 404) : redirect()->route('admin.login');
        }

        return $next($request);
    }
}
