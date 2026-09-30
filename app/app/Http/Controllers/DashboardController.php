<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\PageViewStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * ダッシュボード(ログイン後の既定の画面)を表示する。
     * コンテンツの状況・最近編集したコンテンツ・クイック操作・予約公開・コンテンツの注意事項・最近の操作と、今日・昨日・今月・累計の PV と UU を表示する。
     */
    public function __invoke(Request $request, DashboardService $dashboard, PageViewStatsService $stats): View
    {
        return view('admin.dashboard.index', [
            'counts' => $dashboard->contentCounts(),
            'recentContents' => $dashboard->recentContents(),
            'scheduled' => $dashboard->scheduledContents(),
            'warnings' => $dashboard->contentWarnings(),
            'auditLogs' => $dashboard->recentAuditLogs($request->user('admin')),
            'summary' => $stats->summary(),
        ]);
    }
}
