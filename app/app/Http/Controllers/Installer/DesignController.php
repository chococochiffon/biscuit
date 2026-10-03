<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PageBuilderJsonController;
use App\Installer\BuilderAccess;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Installer\InstallerStepException;
use App\Installer\TemplateInstaller;
use App\Models\PageBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * インストーラーのデザインの段。デフォルトのデザインを使う(「あとで設定する」も同じ)か、ビルダーでトップを作るかを選ぶ。
 * ビルダーは管理画面と同じエディタを、インストーラーの JSON の URL で開く(builder())。使えるのは最初の管理者を作ったセッションだけで
 * (installer.builder)、セッションが切れたら作った管理者のメールアドレスとパスワードで解除する(unlock())。
 * 作った下書きは、公開側(chococo)のプレビューで確かめてから「このデザインで次へ」で公開する。
 */
class DesignController extends Controller
{
    /**
     * 管理者のパスワードによる解除の試行の上限(1 分あたり)。
     */
    public const UNLOCK_ATTEMPTS = 5;

    public function show(Request $request, InstallerManager $installer, BuilderAccess $access): View
    {
        $builder = PageBuilder::top();
        $hasDraft = ($builder?->draft_content['children'] ?? []) !== [];
        $canUseBuilder = $access->administrator($request) !== null;

        return view('installer.design', [
            'installer' => $installer,
            'step' => InstallerStep::Design,
            'canUseBuilder' => $canUseBuilder,
            'hasDraft' => $hasDraft,
            'previewUrl' => $canUseBuilder && $hasDraft ? PageBuilderJsonController::signedPreviewUrl($builder)['url'] : null,
            'selected' => old('design', $request->query('design', $hasDraft ? 'builder' : 'default')),
        ]);
    }

    public function store(Request $request, TemplateInstaller $templates, InstallerManager $installer, BuilderAccess $access): RedirectResponse
    {
        $design = $request->validate(['design' => ['required', 'in:default,builder']])['design'];

        if ($design === 'default') {
            $templates->installDefault();

            return redirect()->route($installer->currentStep()->routeName());
        }

        $administrator = $access->administrator($request);

        if ($administrator === null) {
            return redirect()->route('installer.design', ['design' => 'builder'])->with('error', __('このブラウザでは、ビルダーを使えません。管理者のメールアドレスとパスワードを入れてください。'));
        }

        try {
            $templates->installBuilder($administrator);
        } catch (InstallerStepException $exception) {
            return redirect()->route('installer.design', ['design' => 'builder'])->with('error', $exception->getMessage());
        }

        return redirect()->route($installer->currentStep()->routeName());
    }

    /**
     * ビルダーのエディタ(管理画面と同じ Vue のエディタ)。版の履歴・書き出しと読み込み・公開・テンプレートの保存・
     * グローバルコンポーネントは出さず、公開はデザインの段の「このデザインで次へ」で行う。
     */
    public function builder(): View
    {
        return view('admin.builder.edit', [
            'title' => __('トップページ'),
            'config' => [
                'backUrl' => route('installer.design', ['design' => 'builder']),
                'backLabel' => __('インストーラーに戻る'),
                'canSaveTemplates' => false,
                'endpoints' => [
                    'show' => route('installer.design.builder.json.show'),
                    'update' => route('installer.design.builder.json.update'),
                    'publish' => null,
                    'discard' => null,
                    'previewUrl' => route('installer.design.builder.json.preview-url'),
                    'images' => route('installer.design.builder.json.images'),
                    'templates' => route('installer.design.builder.json.templates'),
                    'articleList' => route('installer.design.builder.json.article-list'),
                    'navigation' => route('installer.design.builder.json.navigation'),
                    'gallery' => route('installer.design.builder.json.gallery'),
                    'components' => null,
                    'versions' => null,
                    'export' => null,
                    'import' => null,
                ],
            ],
        ]);
    }

    /**
     * 作った管理者のメールアドレスとパスワードで、このセッションでもビルダーを使えるようにする。
     */
    public function unlock(Request $request, BuilderAccess $access): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'string'], 'password' => ['required', 'string']]);
        $key = 'installer-builder-unlock:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::UNLOCK_ATTEMPTS)) {
            return redirect()->route('installer.design', ['design' => 'builder'])->with('error', __('試行の回数が多すぎます。:seconds 秒後にもう一度お試しください。', ['seconds' => RateLimiter::availableIn($key)]));
        }

        if (! $access->unlock($request, $credentials['email'], $credentials['password'])) {
            RateLimiter::hit($key);

            return redirect()->route('installer.design', ['design' => 'builder'])->withInput($request->only('email'))->with('error', __('メールアドレスかパスワードが違います。'));
        }

        RateLimiter::clear($key);

        return redirect()->route('installer.design', ['design' => 'builder']);
    }
}
