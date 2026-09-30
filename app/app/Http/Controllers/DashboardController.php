<?php

namespace App\Http\Controllers;

use App\Services\PageViewStatsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * ダッシュボード(ログイン後の既定の画面)を表示する。今日・昨日・今月・累計の PV と UU を表示し、アクセス解析へリンクする。
     */
    public function __invoke(PageViewStatsService $stats): View
    {
        return view('admin.dashboard.index', [
            'summary' => $stats->summary(),
        ]);
    }
}
