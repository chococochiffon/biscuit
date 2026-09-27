<?php

namespace Tests\Feature;

use App\Enums\ArticleApprovalStatus;
use App\Models\Article;
use App\Models\Tag;
use Database\Seeders\ArticleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_published_articles_with_paths_and_tags_even_without_model_events(): void
    {
        $this->freezeSecond();

        // DatabaseSeeder(WithoutModelEvents)から呼ばれる場合と同じく、モデルイベントを止めて実行する
        Model::withoutEvents(fn () => $this->seed(ArticleSeeder::class));

        $articles = Article::query()->with('tags')->orderBy('id')->get();

        $this->assertSame(
            ['/blog/welcome-to-biscuit', '/blog/life/spring-cafe', '/news/biscuit-v1-0-release'],
            $articles->pluck('path')->all()
        );

        foreach ($articles as $article) {
            $this->assertSame(ArticleApprovalStatus::Published, $article->approval);
            $this->assertTrue($article->publication_start_datetime->equalTo(now()));
            $this->assertNull($article->publication_end_datetime);
            $this->assertNull($article->user_id);
        }

        $this->assertSame(['biscuit', 'CMS', 'はじめに'], $articles[0]->tags->pluck('tag_name')->all());
        $this->assertSame(['カフェ', '暮らし', '春', 'おすすめ'], $articles[1]->tags->pluck('tag_name')->all());
        $this->assertSame(['biscuit', 'アップデート', 'リリース', 'お知らせ'], $articles[2]->tags->pluck('tag_name')->all());
        $this->assertSame(10, Tag::count());
    }

    public function test_seeding_twice_does_not_duplicate_articles_or_tags(): void
    {
        $this->seed(ArticleSeeder::class);
        $this->seed(ArticleSeeder::class);

        $this->assertSame(3, Article::count());
        $this->assertSame(10, Tag::count());
        $this->assertDatabaseCount('article_tag', 11);
    }

    public function test_seeded_article_can_be_resolved_by_its_path(): void
    {
        Model::withoutEvents(fn () => $this->seed(ArticleSeeder::class));

        $response = $this->getJson(route('api.resolve', ['path' => '/blog/life/spring-cafe']));

        $response->assertOk()
            ->assertJsonPath('type', 'article')
            ->assertJsonPath('data.title', '春に訪れたいおすすめカフェ3選');
    }
}
