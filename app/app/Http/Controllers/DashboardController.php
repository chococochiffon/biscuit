<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\MediaStatsService;
use App\Services\PageViewStatsService;
use App\Services\SystemStatusService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * ダッシュボード(ログイン後の既定の画面)を表示する。
     * コンテンツの状況・最近編集したコンテンツ・クイック操作・予約公開・コンテンツの注意事項・最近の操作・ユーザー状況・メディア状況と、
     * 今日・昨日・今月・累計の PV と UU を表示する。システム情報と警告はスーパー管理者だけに表示する。
     */
    public function __invoke(
        Request $request,
        DashboardService $dashboard,
        PageViewStatsService $stats,
        MediaStatsService $media,
        SystemStatusService $system,
    ): View {
        $administrator = $request->user('admin');
        $systemStatus = null;

        if ($administrator->can('view-system-status')) {
            $info = $system->info();
            $errors = $system->recentErrors();
            $systemStatus = ['info' => $info, 'errors' => $errors, 'warnings' => $system->warnings($info, $errors)];
        }

        return view('admin.dashboard.index', [
            'counts' => $dashboard->contentCounts(),
            'recentContents' => $dashboard->recentContents(),
            'scheduled' => $dashboard->scheduledContents(),
            'warnings' => $dashboard->contentWarnings(),
            'auditLogs' => $dashboard->recentAuditLogs($administrator),
            'userStatus' => $dashboard->userStatus(),
            'media' => $media->stats(),
            'systemStatus' => $systemStatus,
            'summary' => $stats->summary(),
        ]);
    }
}
