<?php

namespace App\Services;

use App\Models\Article;
use App\Models\CustomPages\CustomPageEntry;
use App\Models\CustomPageType;
use App\Models\PageView;
use App\Models\SinglePage;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * PV(page_views)を集計する(管理画面のアクセス解析)。
 * PV は表示された回数、UU は期間内にアクセスした訪問者(visitor_id)の数。期間はどれも両端を含む。
 * 今は page_views から直接数える。記録が増えて重くなったら、日別の集計テーブルを読むようにここだけを変える。
 */
class PageViewStatsService
{
    /**
     * 期間内の PV(期間を省略すると累計)。
     */
    public function views(?CarbonInterface $from = null, ?CarbonInterface $to = null): int
    {
        return $this->query($from, $to)->count();
    }

    /**
     * 期間内の UU(期間を省略すると累計)。
     */
    public function uniqueVisitors(?CarbonInterface $from = null, ?CarbonInterface $to = null): int
    {
        return $this->query($from, $to)->distinct()->count('visitor_id');
    }

    /**
     * 今日・昨日・今月・累計の PV と UU。
     *
     * @return array<string, array{views: int, unique_visitors: int}>
     */
    public function summary(): array
    {
        $today = CarbonImmutable::today();
        $periods = [
            'today' => [$today, $today->endOfDay()],
            'yesterday' => [$today->subDay(), $today->subDay()->endOfDay()],
            'this_month' => [$today->startOfMonth(), $today->endOfDay()],
            'total' => [null, null],
        ];

        return array_map(fn (array $period) => [
            'views' => $this->views(...$period),
            'unique_visitors' => $this->uniqueVisitors(...$period),
        ], $periods);
    }

    /**
     * 期間内の日別の PV と UU(古い順。記録のない日も 0 で含める)。
     *
     * @return Collection<int, array{date: string, views: int, unique_visitors: int}>
     */
    public function daily(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $rows = $this->query($from->copy()->startOfDay(), $to->copy()->endOfDay())
            ->toBase()
            ->selectRaw('DATE(viewed_at) as date, COUNT(*) as views, COUNT(DISTINCT visitor_id) as unique_visitors')
            ->groupByRaw('DATE(viewed_at)')
            ->get()
            ->keyBy(fn (object $row) => (string) $row->date);

        return collect(CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay()))
            ->map(function (CarbonInterface $day) use ($rows) {
                $row = $rows->get($day->toDateString());

                return [
                    'date' => $day->toDateString(),
                    'views' => (int) ($row->views ?? 0),
                    'unique_visitors' => (int) ($row->unique_visitors ?? 0),
                ];
            })
            ->values();
    }

    /**
     * 期間内の人気コンテンツ(PV の多い順。同じなら UU の多い順)。
     * 各行は種類・id・PV・UU と、表示名(label。記事・固定ページなどはタイトル)・種類の表示名(type_label)・パス。
     *
     * @return Collection<int, array{content_type: string, content_id: int|null, views: int, unique_visitors: int, path: string, label: string, type_label: string}>
     */
    public function ranking(?CarbonInterface $from = null, ?CarbonInterface $to = null, ?int $limit = null): Collection
    {
        $rows = $this->query($from, $to)
            ->toBase()
            ->select(['content_type', 'content_id'])
            ->selectRaw('COUNT(*) as views, COUNT(DISTINCT visitor_id) as unique_visitors, MAX(path) as path')
            ->groupBy(['content_type', 'content_id'])
            ->orderByDesc('views')
            ->orderByDesc('unique_visitors')
            ->orderBy('content_type')
            ->orderBy('content_id')
            ->limit($limit ?? config('page_views.ranking_limit'))
            ->get();

        $labels = $this->contentLabels($rows);

        return $rows->map(fn (object $row) => [
            'content_type' => $row->content_type,
            'content_id' => $row->content_id === null ? null : (int) $row->content_id,
            'views' => (int) $row->views,
            'unique_visitors' => (int) $row->unique_visitors,
            'path' => $row->path,
            'label' => $labels[$row->content_type.'|'.$row->content_id] ?? $row->path,
            'type_label' => PageView::labelForContentType($row->content_type),
        ]);
    }

    /**
     * 1 件のコンテンツの期間内の PV(期間を省略すると累計)。
     */
    public function contentViews(string $contentType, ?int $contentId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): int
    {
        return $this->query($from, $to)
            ->where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->count();
    }

    /**
     * @return Builder<PageView>
     */
    private function query(?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        return PageView::query()
            ->when($from !== null && $to !== null, fn (Builder $query) => $query->viewedBetween($from, $to))
            ->when($from !== null && $to === null, fn (Builder $query) => $query->where('viewed_at', '>=', $from))
            ->when($from === null && $to !== null, fn (Builder $query) => $query->where('viewed_at', '<=', $to));
    }

    /**
     * ランキングの行の表示名(「種類|id」→ 名前)。記事・固定ページ・カスタムページは削除済みでもタイトルを出す。
     *
     * @param  Collection<int, object>  $rows
     * @return array<string, string>
     */
    private function contentLabels(Collection $rows): array
    {
        $labels = [];

        foreach ($rows->groupBy('content_type') as $contentType => $group) {
            $ids = $group->pluck('content_id')->filter()->all();
            $titles = match (true) {
                $contentType === 'article' => Article::withTrashed()->whereKey($ids)->pluck('title', 'id'),
                $contentType === 'single_page' => SinglePage::withTrashed()->whereKey($ids)->pluck('title', 'id'),
                str_starts_with($contentType, 'custom_page:') => $this->customPageTitles(Str::after($contentType, 'custom_page:'), $ids),
                default => collect(),
            };

            foreach ($titles as $id => $title) {
                $labels[$contentType.'|'.$id] = (string) $title;
            }

            if ($contentType === 'top') {
                $labels['top|'] = __('トップ');
            }

            if (str_starts_with($contentType, 'custom_page_list:')) {
                $type = CustomPageType::withTrashed()->where('name', Str::after($contentType, 'custom_page_list:'))->orderBy('deleted_at')->first();
                $labels[$contentType.'|'] = __(':label 一覧', ['label' => $type->label ?? Str::after($contentType, 'custom_page_list:')]);
            }
        }

        return $labels;
    }

    /**
     * カスタムページのタイトル(id → タイトル)。種類がなくなっていれば空。
     *
     * @param  list<int>  $ids
     * @return Collection<int, string>
     */
    private function customPageTitles(string $typeName, array $ids): Collection
    {
        $type = CustomPageType::withTrashed()->where('name', $typeName)->orderBy('deleted_at')->first();

        return $type === null ? collect() : CustomPageEntry::queryFor($type)->withTrashed()->whereKey($ids)->pluck('title', 'id');
    }
}
