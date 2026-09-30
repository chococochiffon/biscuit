<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\PageView;
use App\Models\SinglePage;
use App\Services\PageViewStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PV の集計(今日・昨日・今月・累計、日別の推移、人気コンテンツのランキング、UU)。
 */
class PageViewStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private PageViewStatsService $stats;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 12:00:00');
        $this->stats = new PageViewStatsService;
    }

    public function test_summary_counts_today_yesterday_this_month_and_total(): void
    {
        PageView::factory()->count(3)->create(['viewed_at' => '2026-10-15 00:00:00']);
        PageView::factory()->count(2)->create(['viewed_at' => '2026-10-14 23:59:59']);
        PageView::factory()->create(['viewed_at' => '2026-10-01 00:00:00']);
        PageView::factory()->create(['viewed_at' => '2026-09-30 23:59:59']);

        $summary = $this->stats->summary();

        $this->assertSame(3, $summary['today']['views']);
        $this->assertSame(2, $summary['yesterday']['views']);
        $this->assertSame(6, $summary['this_month']['views']);
        $this->assertSame(7, $summary['total']['views']);
    }

    public function test_unique_visitors_count_distinct_visitor_ids(): void
    {
        $visitor = '11111111-1111-4111-8111-111111111111';
        PageView::factory()->count(3)->create(['visitor_id' => $visitor, 'viewed_at' => now()]);
        PageView::factory()->create(['visitor_id' => '22222222-2222-4222-8222-222222222222', 'viewed_at' => now()]);
        PageView::factory()->create(['visitor_id' => $visitor, 'viewed_at' => now()->subDay()]);

        $today = CarbonImmutable::today();

        $this->assertSame(4, $this->stats->views($today, $today->endOfDay()));
        $this->assertSame(2, $this->stats->uniqueVisitors($today, $today->endOfDay()));
        $this->assertSame(2, $this->stats->uniqueVisitors());
        $this->assertSame(2, $this->stats->summary()['today']['unique_visitors']);
        $this->assertSame(1, $this->stats->summary()['yesterday']['unique_visitors']);
    }

    public function test_daily_views_include_days_without_views(): void
    {
        $visitor = '11111111-1111-4111-8111-111111111111';
        PageView::factory()->count(2)->create(['visitor_id' => $visitor, 'viewed_at' => '2026-10-13 09:00:00']);
        PageView::factory()->create(['viewed_at' => '2026-10-15 23:00:00']);
        PageView::factory()->create(['viewed_at' => '2026-10-12 23:59:59']);

        $daily = $this->stats->daily(CarbonImmutable::parse('2026-10-13'), CarbonImmutable::parse('2026-10-15'));

        $this->assertSame([
            ['date' => '2026-10-13', 'views' => 2, 'unique_visitors' => 1],
            ['date' => '2026-10-14', 'views' => 0, 'unique_visitors' => 0],
            ['date' => '2026-10-15', 'views' => 1, 'unique_visitors' => 1],
        ], $daily->all());
    }

    public function test_ranking_orders_contents_by_views_within_the_period(): void
    {
        $laravel = Article::factory()->published()->create(['title' => 'Laravel入門']);
        $docker = Article::factory()->published()->create(['title' => 'Docker入門']);
        $about = SinglePage::factory()->create(['title' => '会社概要']);
        $about->delete();

        PageView::factory()->count(3)->forContent('article', $laravel->id, '/laravel')->create();
        PageView::factory()->count(5)->forContent('article', $docker->id, '/docker')->create();
        PageView::factory()->count(4)->forContent('single_page', $about->id, '/about')->create();
        PageView::factory()->count(2)->create();
        // 期間外
        PageView::factory()->count(10)->forContent('article', $laravel->id, '/laravel')->create(['viewed_at' => now()->subMonth()]);

        $ranking = $this->stats->ranking(CarbonImmutable::today(), CarbonImmutable::today()->endOfDay());

        $this->assertSame(
            [['Docker入門', 5], ['会社概要', 4], ['Laravel入門', 3], ['トップ', 2]],
            $ranking->map(fn (array $row) => [$row['label'], $row['views']])->all(),
        );
        $this->assertSame(['記事', '固定ページ', '記事', 'トップ'], $ranking->pluck('type_label')->all());
        $this->assertSame([$docker->id, $about->id, $laravel->id, null], $ranking->pluck('content_id')->all());

        // 期間を省略すると累計、件数を指定すると上位だけ
        $this->assertSame(['Laravel入門'], $this->stats->ranking(limit: 1)->pluck('label')->all());
    }

    public function test_ranking_counts_unique_visitors_per_content(): void
    {
        $article = Article::factory()->published()->create();
        $visitor = '11111111-1111-4111-8111-111111111111';
        PageView::factory()->count(3)->forContent('article', $article->id)->create(['visitor_id' => $visitor]);
        PageView::factory()->forContent('article', $article->id)->create();

        $row = $this->stats->ranking()->sole();

        $this->assertSame(4, $row['views']);
        $this->assertSame(2, $row['unique_visitors']);
    }

    public function test_content_views_count_a_single_content(): void
    {
        PageView::factory()->count(2)->forContent('article', 1)->create();
        PageView::factory()->forContent('article', 1)->create(['viewed_at' => now()->subDays(3)]);
        PageView::factory()->forContent('article', 2)->create();
        PageView::factory()->forContent('single_page', 1)->create();

        $this->assertSame(3, $this->stats->contentViews('article', 1));
        $this->assertSame(2, $this->stats->contentViews('article', 1, CarbonImmutable::today()));
    }
}
