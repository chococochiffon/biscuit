<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePageBuilderThemeRequest;
use App\Models\PageBuilderTheme;
use App\Support\AuditLogger;
use App\Support\Builder\ThemeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * ページビルダーのテーマ(ビルダーのブロックにだけ効く色とフォント)の編集。テーマはサイトに 1 つで、最初の保存で登録する。
 */
class PageBuilderThemeController extends Controller
{
    /**
     * テーマの編集画面を表示する。
     */
    public function edit(): View
    {
        return view('admin.builder_theme.edit', [
            'theme' => PageBuilderTheme::current(),
            'colors' => ThemeRegistry::COLORS,
            'fonts' => ThemeRegistry::FONTS,
        ]);
    }

    /**
     * テーマを保存する(未登録なら登録する)。
     */
    public function update(UpdatePageBuilderThemeRequest $request): RedirectResponse
    {
        $theme = PageBuilderTheme::current();

        if ($theme->exists) {
            AuditLogger::updateWithLog($theme, fn () => $theme->update($request->themeAttributes()));
        } else {
            AuditLogger::createWithLog(fn () => PageBuilderTheme::query()->create($request->themeAttributes()));
        }

        return redirect()->route('admin.builder-theme.edit')->with('status', __('テーマを保存しました。'));
    }
}
