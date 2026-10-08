<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\PageBuilder;
use App\Models\SinglePage;
use App\Models\Tag;
use App\Support\Builder\ArticleListQuery;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleListBlockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 公開開始日時が 1 日ずつ新しい公開済みの記事。
     *
     * @return list<Article>
     */
    private function articles(): array
    {
        $this->travelTo('2026-10-10 12:00:00');

        return [
            Article::factory()->published()->create(['title' => '古いお知らせ', 'parent_path' => 'news', 'publication_start_datetime' => '2026-10-01 09:00:00']),
            Article::factory()->published()->create(['title' => 'ブログ', 'parent_path' => 'blog', 'publication_start_datetime' => '2026-10-02 09:00:00']),
            Article::factory()->published()->create(['title' => '新しいお知らせ', 'parent_path' => 'news', 'publication_start_datetime' => '2026-10-03 09:00:00']),
        ];
    }

    public function test_query_returns_published_articles_by_the_conditions(): void
    {
        [$old, $blog, $new] = $this->articles();
        Article::factory()->create(['title' => '下書き', 'parent_path' => 'news']);
        Article::factory()->published()->create(['title' => '予約公開', 'parent_path' => 'news', 'publication_start_datetime' => '2026-10-20 09:00:00']);
        $tag = Tag::factory()->create(['tag_name' => 'イベント']);
        $blog->tags()->attach($tag);

        $titles = fn (array $props) => ArticleListQuery::articles($props)->pluck('title')->all();

        $this->assertSame(['新しいお知らせ', 'ブログ', '古いお知らせ'], $titles([]));
        $this->assertSame(['古いお知らせ', 'ブログ'], $titles(['order' => 'oldest', 'limit' => 2]));
        $this->assertSame(['新しいお知らせ', '古いお知らせ'], $titles(['parentPath' => '/news/']));
        $this->assertSame(['ブログ'], $titles(['tag' => 'イベント']));
        $this->assertSame([], $titles(['tag' => 'ない']));
    }

    public function test_resolve_returns_the_articles_of_the_block_without_saving_them(): void
    {
        $this->articles();
        $singlePage = SinglePage::factory()->create(['slug' => 'news-page', 'use_builder' => true]);
        $builder = PageBuilder::factory()->published()->create([
            'single_page_id' => $singlePage->id,
            'draft_content' => BuilderContent::withDefaultLayout([
                'version' => SchemaMigrator::CURRENT_VERSION,
                'children' => [BuilderContent::node('section', children: [BuilderContent::node('article-list', ['limit' => 2, 'parentPath' => 'news'])])],
            ]),
        ]);

        $this->getJson(route('api.resolve', ['path' => '/news-page']))
            ->assertOk()
            ->assertJsonPath('data.builder.children.0.children.0.type', 'article-list')
            ->assertJsonPath('data.builder.children.0.children.0.data.articles.0.title', '新しいお知らせ')
            ->assertJsonPath('data.builder.children.0.children.0.data.articles.1.title', '古いお知らせ')
            ->assertJsonCount(2, 'data.builder.children.0.children.0.data.articles');

        // 保存している内容には、取得の条件だけを持つ
        $this->assertArrayNotHasKey('data', $builder->fresh()->published_content['children'][0]['children'][0]);
    }

    public function test_editor_preview_returns_the_articles_by_the_conditions(): void
    {
        $this->articles();

        $this->getJson(route('admin.json.builder.article-list', ['limit' => 1]))->assertUnauthorized();

        $this->actingAsAdmin();

        $this->getJson(route('admin.json.builder.article-list', ['limit' => 1, 'order' => 'oldest', 'parentPath' => 'news']))
            ->assertOk()
            ->assertJsonCount(1, 'articles')
            ->assertJsonPath('articles.0.title', '古いお知らせ');
    }

    public function test_saving_keeps_only_the_conditions(): void
    {
        $this->actingAsAdmin();
        $content = BuilderContent::withDefaultLayout([
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [BuilderContent::node('section', children: [BuilderContent::node('article-list', ['showDate' => false, 'layout' => 'list'])])],
        ]);

        $this->putJson(route('admin.json.builder.top.update'), ['content' => $content, 'updated_at' => null])->assertOk();

        $this->assertSame(
            ['limit' => 6, 'order' => 'newest', 'parentPath' => '', 'tag' => '', 'layout' => 'list', 'columns' => 3, 'showExcerpt' => true, 'showDate' => false],
            PageBuilder::top()->draft_content['children'][0]['children'][0]['props'],
        );
    }
}
