<?php

namespace App\Http\Middleware\Installer;

use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * インストールを終えるまでは、管理画面・API をインストーラーへ回す(API は 503)。
 * DB を使うミドルウェア(セッションなど)より前に置く(web・api のグループの先頭)。
 * ただし、アプリケーションの段(DB とテーブルを作る)を終えたあとの API の読み取り(GET)は通す。
 * インストーラーのデザインの段のプレビューで、公開側(chococo)がプレビュー・サイト設定・レイアウトの API を読むため
 * (返すのは公開する内容と、署名付きのプレビューだけ。書き込み・ログインはインストールを終えるまで止めたまま)。
 */
class RedirectIfNotInstalled
{
    public function __construct(private InstallerManager $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installer->isInstalled() || $this->allowsReadingApi($request)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => __('Biscuit のインストールが済んでいません。')], 503);
        }

        return redirect()->route('installer.requirements');
    }

    private function allowsReadingApi(Request $request): bool
    {
        return $request->is('api/*')
            && $request->isMethodSafe()
            && $this->installer->state()->isCompleted(InstallerStep::Application);
    }
}
