<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * 選択された言語をセッションに保存し、直前のページへ戻る。
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, config('app.available_locales'), true), 404);

        $request->session()->put('locale', $locale);

        return back();
    }
}
