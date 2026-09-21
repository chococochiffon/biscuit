<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdministratorLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdministratorSessionController extends Controller
{
    /**
     * ログイン画面を表示する。
     */
    public function create(): View
    {
        return view('admin.auth.login');
    }

    /**
     * ログイン処理を行う。
     */
    public function store(AdministratorLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        Auth::guard('admin')->user()->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->route('admin.articles.index');
    }

    /**
     * ログアウト処理を行う。
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
