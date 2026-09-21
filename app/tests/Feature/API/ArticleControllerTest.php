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

    public function test_show_returns_published_article(): void
    {
        $article = Article::factory()->create([
            'title' => '公開記事',
            'approval' => ArticleApprovalStatus::Published,
        ]);

        $response = $this->getJson(route('articles.show', $article));

        $response->assertOk();
        $response->assertJsonPath('data.id', $article->id);
        $response->assertJsonPath('data.title', $article->title);
    }

    public function test_show_returns_404_for_draft_article(): void
    {
        $article = Article::factory()->create(['approval' => ArticleApprovalStatus::Draft]);

        $response = $this->getJson(route('articles.show', $article));

        $response->assertNotFound();
    }

    public function test_show_returns_404_for_soft_deleted_article(): void
    {
        $article = Article::factory()->create(['approval' => ArticleApprovalStatus::Published]);
        $articleId = $article->id;
        $article->delete();

        $response = $this->getJson("/api/articles/{$articleId}");

        $response->assertNotFound();
    }
}
