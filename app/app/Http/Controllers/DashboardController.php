<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * ダッシュボード(ログイン後の既定の画面)を表示する。表示する項目はこれから決めるため、今は見出しだけ。
     */
    public function __invoke(): View
    {
        return view('admin.dashboard.index');
    }
}
