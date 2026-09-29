<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Article;
use App\Models\ArticlePathOption;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticlePathOptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_manage_path_options(): void
    {
        $this->getJson(route('admin.article-path-options.index'))->assertUnauthorized();
    }

    public function test_index_returns_options_in_sort_order(): void
    {
        $this->actingAsAdmin();
        $second = ArticlePathOption::factory()->create(['label' => '暮らし', 'parent_path' => 'blog/life', 'sort_order' => 1]);
        $first = ArticlePathOption::factory()->create(['label' => 'ブログ', 'parent_path' => 'blog', 'sort_order' => 0]);

        $response = $this->getJson(route('admin.article-path-options.index'));

        $response->assertOk();
        $this->assertSame([$first->id, $second->id], array_column($response->json(), 'id'));
        $response->assertJsonPath('0.parent_path', 'blog');
    }

    public function test_store_creates_option_at_the_end_and_trims_slashes(): void
    {
        $this->actingAsAdmin();
        ArticlePathOption::factory()->create(['sort_order' => 3]);

        $this->postJson(route('admin.article-path-options.store'), ['label' => '技術', 'parent_path' => '/blog/tech/'])
            ->assertCreated()
            ->assertJsonPath('parent_path', 'blog/tech');

        $option = ArticlePathOption::where('label', '技術')->firstOrFail();
        $this->assertSame(4, $option->sort_order);
        $this->assertTrue(AuditLog::query()->where('subject_type', 'article_path_option')->where('action', AuditAction::Created)->exists());
    }

    public function test_store_rejects_invalid_or_duplicate_parent_path(): void
    {
        $this->actingAsAdmin();
        ArticlePathOption::factory()->create(['parent_path' => 'blog']);
        ArticlePathOption::factory()->create(['parent_path' => 'old'])->delete();

        $this->postJson(route('admin.article-path-options.store'), ['label' => 'x', 'parent_path' => 'Blog Tech'])->assertJsonValidationErrors('parent_path');
        $this->postJson(route('admin.article-path-options.store'), ['label' => 'x', 'parent_path' => 'blog'])->assertJsonValidationErrors('parent_path');
        $this->postJson(route('admin.article-path-options.store'), ['label' => '', 'parent_path' => ''])->assertJsonValidationErrors(['label', 'parent_path']);
        // 削除済みの投稿先と同じ親パスは登録できる
        $this->postJson(route('admin.article-path-options.store'), ['label' => 'x', 'parent_path' => 'old'])->assertCreated();
    }

    public function test_store_rejects_when_limit_is_reached(): void
    {
        config(['limits.article_path_options' => 1]);
        $this->actingAsAdmin();
        ArticlePathOption::factory()->create();

        $this->postJson(route('admin.article-path-options.store'), ['label' => 'x', 'parent_path' => 'news'])->assertJsonValidationErrors('label');
    }

    public function test_update_changes_option_without_changing_existing_article_paths(): void
    {
        $this->actingAsAdmin();
        $option = ArticlePathOption::factory()->create(['label' => 'ブログ', 'parent_path' => 'blog']);
        $article = Article::factory()->create(['parent_path' => 'blog', 'slug' => 'hello']);

        $this->putJson(route('admin.article-path-options.update', $option), ['label' => '日記', 'parent_path' => 'diary'])->assertOk();

        $this->assertSame('diary', $option->fresh()->parent_path);
        $this->assertSame('/blog/hello', $article->fresh()->path);
        // 自分自身の親パスのままでも更新できる
        $this->putJson(route('admin.article-path-options.update', $option), ['label' => '日記2', 'parent_path' => 'diary'])->assertOk();
    }

    public function test_destroy_soft_deletes_option(): void
    {
        $this->actingAsAdmin();
        $option = ArticlePathOption::factory()->create();

        $this->deleteJson(route('admin.article-path-options.destroy', $option))->assertNoContent();

        $this->assertSoftDeleted($option);
    }

    public function test_reorder_saves_sort_order(): void
    {
        $this->actingAsAdmin();
        $first = ArticlePathOption::factory()->create(['sort_order' => 0]);
        $second = ArticlePathOption::factory()->create(['sort_order' => 1]);

        $this->patchJson(route('admin.article-path-options.reorder'), ['order' => [$second->id, $first->id]])->assertNoContent();

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
    }

    public function test_article_index_shows_path_option_manager_and_pending_badge(): void
    {
        $this->actingAsAdmin();
        Article::factory()->pending()->count(2)->create();

        $this->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('article-path-option-manager-modal', false)
            ->assertSee('承認待ち 2 件');
    }
}
