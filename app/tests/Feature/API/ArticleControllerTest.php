<?php

namespace Tests\Feature\API;

use App\Enums\ArticleApprovalStatus;
use App\Models\Article;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_published_articles(): void
    {
        $published = Article::factory()->create(['title' => '公開記事', 'approval' => ArticleApprovalStatus::Published]);
        Article::factory()->create(['title' => '下書き記事', 'approval' => ArticleApprovalStatus::Draft]);
        Article::factory()->create(['title' => '未承認記事', 'approval' => ArticleApprovalStatus::Pending]);

        $response = $this->getJson(route('articles.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['title' => $published->title]);
    }

    public function test_index_includes_tags(): void
    {
        $article = Article::factory()->create(['approval' => ArticleApprovalStatus::Published]);
        $tag = Tag::factory()->create(['tag_name' => 'Laravel']);
        $article->tags()->sync([$tag->id]);

        $response = $this->getJson(route('articles.index'));

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Laravel']);
    }

    public function test_index_excludes_articles_outside_publication_period(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        Article::factory()->published()->create(['publication_start_datetime' => '2026-10-02 00:00:00']);
        Article::factory()->published()->create(['publication_start_datetime' => '2026-09-01 00:00:00', 'publication_end_datetime' => '2026-09-30 00:00:00']);
        $visible = Article::factory()->published()->create(['publication_start_datetime' => '2026-09-01 00:00:00']);

        $response = $this->getJson(route('articles.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_index_orders_articles_by_publication_start_datetime_desc(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $older = Article::factory()->published()->create(['publication_start_datetime' => '2026-09-10 00:00:00', 'created_at' => '2026-09-20 00:00:00']);
        $newer = Article::factory()->published()->create(['publication_start_datetime' => '2026-09-20 00:00:00', 'created_at' => '2026-09-01 00:00:00']);
        // 公開開始日時が同じ記事は、id の大きい順に並べる
        $sameFirst = Article::factory()->published()->create(['publication_start_datetime' => '2026-09-15 00:00:00']);
        $sameSecond = Article::factory()->published()->create(['publication_start_datetime' => '2026-09-15 00:00:00']);

        $response = $this->getJson(route('articles.index'));

        $response->assertOk();
        $this->assertSame([$newer->id, $sameSecond->id, $sameFirst->id, $older->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_returns_publication_start_datetime_as_published_at(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        Article::factory()->published()->create([
            'publication_start_datetime' => '2026-09-20 09:30:00',
            'created_at' => '2026-09-01 00:00:00',
        ]);

        $response = $this->getJson(route('articles.index'));

        $response->assertOk();
        $response->assertJsonPath('data.0.published_at', '2026-09-20T09:30:00+09:00');
    }
}
