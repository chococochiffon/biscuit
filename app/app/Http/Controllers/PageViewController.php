<?php

namespace App\Http\Controllers;

use App\Services\PageViewStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * アクセス解析(公開側の PV・UU の集計)。閲覧だけで、画面からは記録を変えない。
 */
class PageViewController extends Controller
{
    /**
     * 人気コンテンツのランキングの期間(GET パラメータ period。custom は from・to の日付で指定する)。
     */
    public const PERIODS = ['today', '7days', '30days', 'month', 'custom'];

    public const DEFAULT_PERIOD = '30days';

    /**
     * 日別の推移を表示する日数(今日を含む直近の日数)。
     */
    public const DAILY_DAYS = 30;

    /**
     * 今日・昨日・今月・累計の PV と UU、期間を指定した人気コンテンツのランキング、直近 30 日の日別の推移を表示する。
     * 不正な期間はリダイレクトせずに無視し、既定の期間(直近 30 日)にする。
     */
    public function index(Request $request, PageViewStatsService $stats): View
    {
        $filters = Validator::make($request->query(), [
            'period' => [Rule::in(self::PERIODS)],
            'from' => ['date_format:Y-m-d'],
            'to' => ['date_format:Y-m-d'],
        ])->valid();

        [$period, $from, $to] = $this->rankingPeriod($filters);

        $today = CarbonImmutable::today();
        $summary = $stats->summary();
        $ranking = $stats->ranking($from, $to);
        $rankingViews = $stats->views($from, $to);
        $rankingUniqueVisitors = $stats->uniqueVisitors($from, $to);
        $daily = $stats->daily($today->subDays(self::DAILY_DAYS - 1), $today);
        $dailyMax = max(1, (int) $daily->max('views'));

        return view('admin.page_views.index', compact(
            'summary', 'ranking', 'rankingViews', 'rankingUniqueVisitors', 'daily', 'dailyMax', 'period', 'from', 'to',
        ));
    }

    /**
     * ランキングの期間([期間の種類, 開始日時, 終了日時])。
     * 期間指定(custom)は開始日・終了日の片方だけでもよく、開始日が終了日より後なら入れ替える。
     *
     * @param  array<string, string>  $filters
     * @return array{0: string, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function rankingPeriod(array $filters): array
    {
        $today = CarbonImmutable::today();
        $period = $filters['period'] ?? self::DEFAULT_PERIOD;

        if ($period === 'custom' && (isset($filters['from']) || isset($filters['to']))) {
            $from = CarbonImmutable::parse($filters['from'] ?? $filters['to']);
            $to = CarbonImmutable::parse($filters['to'] ?? $filters['from']);

            return ['custom', $from->min($to)->startOfDay(), $from->max($to)->endOfDay()];
        }

        $from = match ($period) {
            'today' => $today,
            '7days' => $today->subDays(6),
            'month' => $today->startOfMonth(),
            default => $today->subDays(29),
        };

        return [$period === 'custom' ? self::DEFAULT_PERIOD : $period, $from, $today->endOfDay()];
    }
}
