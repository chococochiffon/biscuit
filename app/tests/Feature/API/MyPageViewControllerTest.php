<?php

namespace Tests\Feature\API;

use App\Http\Controllers\API\AuthController;
use App\Models\Article;
use App\Models\PageView;
use App\Models\SinglePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * マイページのダッシュボードの、自分の記事のアクセス(GET /api/me/page-views)。
 */
class MyPageViewControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-15 12:00:00');
        $this->user = User::factory()->create();
    }

    private function actingAsUserWithToken(): void
    {
        $this->withToken($this->user->createToken(AuthController::TOKEN_NAME, expiresAt: now()->addDay())->plainTextToken);
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson(route('api.me.page-views.show'))->assertUnauthorized();
    }

    public function test_only_views_of_own_articles_are_counted(): void
    {
        $this->actingAsUserWithToken();
        $mine = Article::factory()->published()->create(['user_id' => $this->user->id, 'title' => '自分の記事', 'parent_path' => 'news', 'slug' => 'mine']);
        $popular = Article::factory()->published()->create(['user_id' => $this->user->id, 'title' => '人気の記事']);
        $deleted = Article::factory()->published()->create(['user_id' => $this->user->id]);
        $others = Article::factory()->published()->create();
        $singlePage = SinglePage::factory()->create();

        $visitor = '11111111-1111-4111-8111-111111111111';
        PageView::factory()->count(2)->forContent('article', $mine->id, '/news/mine')->create(['visitor_id' => $visitor]);
        PageView::factory()->forContent('article', $mine->id, '/news/mine')->create(['viewed_at' => now()->subDay()]);
        PageView::factory()->count(3)->forContent('article', $popular->id)->create();
        // 31 日前は推移・人気記事の期間外だが、累計には入る
        PageView::factory()->forContent('article', $popular->id)->create(['viewed_at' => now()->subDays(30)]);
        PageView::factory()->forContent('article', $deleted->id)->create();
        PageView::factory()->count(5)->forContent('article', $others->id)->create();
        PageView::factory()->count(5)->forContent('single_page', $mine->id)->create();
        PageView::factory()->count(5)->create();
        $deleted->delete();

        $response = $this->getJson(route('api.me.page-views.show'));

        $response->assertOk();
        $response->assertJsonPath('data.summary.today', ['views' => 5, 'unique_visitors' => 4]);
        $response->assertJsonPath('data.summary.yesterday.views', 1);
        $response->assertJsonPath('data.summary.total.views', 7);
        $response->assertJsonCount(30, 'data.daily');
        $response->assertJsonPath('data.daily.29', ['date' => '2026-10-15', 'views' => 5, 'unique_visitors' => 4]);
        $response->assertJsonPath('data.daily.0.date', '2026-09-16');
        $response->assertJsonPath('data.ranking', [
            // PV が同じなら UU の多い順
            ['article_id' => $popular->id, 'title' => '人気の記事', 'path' => '/page', 'views' => 3, 'unique_visitors' => 3],
            ['article_id' => $mine->id, 'title' => '自分の記事', 'path' => '/news/mine', 'views' => 3, 'unique_visitors' => 2],
        ]);
    }
}
