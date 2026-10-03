<?php

namespace App\Http\Middleware\Installer;

use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * インストールを終えるまでは、管理画面・API をインストーラーへ回す(API は 503)。
 * DB を使うミドルウェア(セッションなど)より前に置く(web・api のグループの先頭)。
 * ただし、アプリケーションの段(DB とテーブルを作る)を終えたあとは、デザインの段のプレビューの API の読み取り(GET)だけを通す。
 * 公開側(chococo)の /builder-preview は、プレビューの署名(id・expires・signature)を X-Biscuit-Preview-* のヘッダーで
 * サイト設定・レイアウトなどの API にも付けて送るため、署名が正しい読み取りだけを通す(署名のない公開側の表示は 503 で、chococo は「準備中」を出す)。
 */
class RedirectIfNotInstalled
{
    public const PREVIEW_ID_HEADER = 'X-Biscuit-Preview-Id';

    public const PREVIEW_EXPIRES_HEADER = 'X-Biscuit-Preview-Expires';

    public const PREVIEW_SIGNATURE_HEADER = 'X-Biscuit-Preview-Signature';

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
            && $this->installer->state()->isCompleted(InstallerStep::Application)
            && $this->hasValidPreviewSignature($request);
    }

    /**
     * プレビューの署名が正しいか。署名はヘッダー(X-Biscuit-Preview-Id・Expires・Signature)か、プレビューの API そのもののパスとクエリで受け取る。
     */
    private function hasValidPreviewSignature(Request $request): bool
    {
        $id = $request->header(self::PREVIEW_ID_HEADER);
        $expires = $request->header(self::PREVIEW_EXPIRES_HEADER);
        $signature = $request->header(self::PREVIEW_SIGNATURE_HEADER);

        if ($id === null && preg_match('#\Aapi/builder-previews/(\d+)\z#', $request->path(), $matches) === 1) {
            [$id, $expires, $signature] = [$matches[1], $request->query('expires'), $request->query('signature')];
        }

        if (! is_string($id) || ! ctype_digit($id) || ! is_string($expires) || ! is_string($signature)) {
            return false;
        }

        $signed = Request::create('/api/builder-previews/'.$id.'?'.http_build_query(['expires' => $expires, 'signature' => $signature]));

        return URL::hasValidSignature($signed, absolute: false);
    }
}
