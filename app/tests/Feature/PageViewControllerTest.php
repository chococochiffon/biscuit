<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理画面のアクセス解析。
 */
class PageViewControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 12:00:00');
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get(route('admin.page-views.index'))->assertRedirect(route('admin.login'));
    }

    public function test_summary_ranking_and_daily_views_are_displayed(): void
    {
        $this->actingAsAdmin();
        $article = Article::factory()->published()->create(['title' => 'Laravel入門']);
        PageView::factory()->count(3)->forContent('article', $article->id, '/news/laravel')->create();
        PageView::factory()->create(['viewed_at' => now()->subDay()]);

        $response = $this->get(route('admin.page-views.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary) => $summary['today']['views'] === 3 && $summary['yesterday']['views'] === 1 && $summary['total']['views'] === 4);
        $response->assertViewHas('daily', fn ($daily) => $daily->count() === 30 && $daily->last()['date'] === '2026-10-15' && $daily->last()['views'] === 3);
        $response->assertViewHas('period', '30days');
        $response->assertSee('data-role="page-view-chart"', false);
        $response->assertSeeInOrder(['直近30日のアクセス推移', '表で見る', '人気コンテンツ', 'Laravel入門', '/news/laravel']);
    }

    public function test_ranking_period_can_be_selected(): void
    {
        $this->actingAsAdmin();
        $old = Article::factory()->published()->create(['title' => '先月の記事']);
        $new = Article::factory()->published()->create(['title' => '今日の記事']);
        PageView::factory()->forContent('article', $old->id)->create(['viewed_at' => '2026-09-20 10:00:00']);
        PageView::factory()->forContent('article', $new->id)->create();

        $this->get(route('admin.page-views.index', ['period' => 'today']))
            ->assertOk()
            ->assertViewHas('ranking', fn ($ranking) => $ranking->pluck('label')->all() === ['今日の記事']);

        $this->get(route('admin.page-views.index', ['period' => 'month']))
            ->assertViewHas('from', fn ($from) => $from->toDateString() === '2026-10-01')
            ->assertViewHas('ranking', fn ($ranking) => $ranking->pluck('label')->all() === ['今日の記事']);

        // 期間指定は開始日と終了日が逆でもよい
        $this->get(route('admin.page-views.index', ['period' => 'custom', 'from' => '2026-09-30', 'to' => '2026-09-01']))
            ->assertViewHas('period', 'custom')
            ->assertViewHas('ranking', fn ($ranking) => $ranking->pluck('label')->all() === ['先月の記事']);
    }

    public function test_invalid_period_falls_back_to_the_last_30_days(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.page-views.index', ['period' => 'forever', 'from' => 'yesterday']))
            ->assertOk()
            ->assertViewHas('period', '30days')
            ->assertViewHas('from', fn ($from) => $from->toDateString() === '2026-09-16');
    }
}
