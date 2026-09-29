<?php

namespace Tests\Feature\API;

use App\Enums\ArticleApprovalStatus;
use App\Enums\AuditAction;
use App\Http\Controllers\API\AuthController;
use App\Models\Article;
use App\Models\ArticlePathOption;
use App\Models\AuditLog;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MyArticleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ArticlePathOption $blog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->blog = ArticlePathOption::factory()->create(['label' => 'ブログ', 'parent_path' => 'blog']);
    }

    private function actingAsUserWithToken(?User $user = null): void
    {
        $this->withToken(($user ?? $this->user)->createToken(AuthController::TOKEN_NAME, expiresAt: now()->addDay())->plainTextToken);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function articleInput(array $overrides = []): array
    {
        return [
            'title' => 'はじめての投稿',
            'content' => '<p>本文です</p>',
            'article_path_option_id' => $this->blog->id,
            'slug' => 'first-post',
            'tags' => ['日記', '猫'],
            ...$overrides,
        ];
    }

    public function test_guests_cannot_use_my_article_api(): void
    {
        $this->getJson(route('api.me.articles.index'))->assertUnauthorized();
        $this->postJson(route('api.me.articles.store'), $this->articleInput())->assertUnauthorized();
        $this->getJson(route('api.me.article-paths.index'))->assertUnauthorized();
    }

    public function test_index_returns_only_own_articles_and_filters_by_approval(): void
    {
        $draft = Article::factory()->for($this->user)->create();
        $pending = Article::factory()->for($this->user)->pending()->create();
        Article::factory()->pending()->create();
        Article::factory()->for($this->user)->create()->delete();
        $this->actingAsUserWithToken();

        $response = $this->getJson(route('api.me.articles.index'))->assertOk();
        $this->assertEqualsCanonicalizing([$draft->id, $pending->id], array_column($response->json('data'), 'id'));

        $this->getJson(route('api.me.articles.index', ['approval' => 'pending']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.0.approval', 'pending');
    }

    public function test_article_paths_returns_options_in_sort_order(): void
    {
        $this->blog->update(['sort_order' => 1]);
        $news = ArticlePathOption::factory()->create(['label' => 'お知らせ', 'parent_path' => 'news', 'sort_order' => 0]);
        ArticlePathOption::factory()->create()->delete();
        $this->actingAsUserWithToken();

        $this->getJson(route('api.me.article-paths.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $news->id)
            ->assertJsonPath('data.1.parent_path', 'blog');
    }

    public function test_store_creates_draft_under_selected_path_with_tags_and_audit_log(): void
    {
        Tag::factory()->create(['tag_name' => '猫']);
        $this->actingAsUserWithToken();

        $response = $this->postJson(route('api.me.articles.store'), $this->articleInput([
            'parent_path' => 'ignored',
            'approval' => 'published',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.path', '/blog/first-post')
            ->assertJsonPath('data.approval', 'draft');

        $article = Article::query()->where('title', 'はじめての投稿')->firstOrFail();
        $this->assertSame($this->user->id, $article->user_id);
        $this->assertSame(ArticleApprovalStatus::Draft, $article->approval);
        $this->assertSame('blog', $article->parent_path);
        $this->assertEqualsCanonicalizing(['日記', '猫'], $article->tags->pluck('tag_name')->all());
        $this->assertSame(2, Tag::count());

        $log = AuditLog::query()->where('subject_type', 'article')->where('subject_id', $article->id)->firstOrFail();
        $this->assertSame(AuditAction::Created, $log->action);
        $this->assertSame('user', $log->actor_type);
    }

    public function test_store_uses_article_id_in_path_when_slug_is_empty(): void
    {
        $this->actingAsUserWithToken();

        $response = $this->postJson(route('api.me.articles.store'), $this->articleInput(['slug' => '']))->assertCreated();

        $this->assertSame('/blog/'.$response->json('data.id'), $response->json('data.path'));
    }

    public function test_store_requires_valid_path_option_and_rejects_taken_path(): void
    {
        $deleted = ArticlePathOption::factory()->create(['parent_path' => 'old']);
        $deleted->delete();
        Article::factory()->create(['parent_path' => 'blog', 'slug' => 'taken']);
        $this->actingAsUserWithToken();

        $this->postJson(route('api.me.articles.store'), $this->articleInput(['article_path_option_id' => null]))->assertJsonValidationErrors('article_path_option_id');
        $this->postJson(route('api.me.articles.store'), $this->articleInput(['article_path_option_id' => $deleted->id]))->assertJsonValidationErrors('article_path_option_id');
        $this->postJson(route('api.me.articles.store'), $this->articleInput(['slug' => 'taken']))->assertJsonValidationErrors('slug');
        $this->postJson(route('api.me.articles.store'), $this->articleInput(['slug' => '123']))->assertJsonValidationErrors('slug');
    }

    public function test_store_sanitizes_content(): void
    {
        $this->actingAsUserWithToken();
        $ownImage = Article::contentImageUrlPrefix().'photo.png';

        $this->postJson(route('api.me.articles.store'), $this->articleInput([
            'content' => '<h2 onclick="x()">見出し</h2><p>本文<script>alert(1)</script></p><img src="'.$ownImage.'" onerror="x()"><img src="https://evil.example.com/a.png">',
        ]))->assertCreated();

        $this->assertSame(
            '<h2>見出し</h2><p>本文</p><img src="'.$ownImage.'">',
            Article::query()->where('title', 'はじめての投稿')->value('content')
        );
    }

    public function test_store_rejects_content_that_is_empty_after_sanitizing(): void
    {
        $this->actingAsUserWithToken();

        $this->postJson(route('api.me.articles.store'), $this->articleInput(['content' => '<script>alert(1)</script>']))
            ->assertJsonValidationErrors('content');
    }

    public function test_other_users_articles_are_not_found(): void
    {
        $other = Article::factory()->create();
        $this->actingAsUserWithToken();

        $this->getJson(route('api.me.articles.show', $other))->assertNotFound();
        $this->putJson(route('api.me.articles.update', $other), $this->articleInput())->assertNotFound();
        $this->postJson(route('api.me.articles.submit', $other))->assertNotFound();
        $this->deleteJson(route('api.me.articles.destroy', $other))->assertNotFound();
        $this->assertNotSoftDeleted($other);
    }

    public function test_show_returns_own_article_with_review_comment(): void
    {
        $article = Article::factory()->for($this->user)->create(['review_comment' => '誤字を直してください']);
        $this->actingAsUserWithToken();

        $this->getJson(route('api.me.articles.show', $article))
            ->assertOk()
            ->assertJsonPath('data.id', $article->id)
            ->assertJsonPath('data.review_comment', '誤字を直してください');
    }

    public function test_update_keeps_parent_path_when_path_option_is_not_sent(): void
    {
        // 投稿先が削除されていても、選び直さなければ今の親パスのまま保存できる
        $article = Article::factory()->for($this->user)->create(['parent_path' => 'old', 'slug' => 'post']);
        $this->actingAsUserWithToken();

        $this->putJson(route('api.me.articles.update', $article), $this->articleInput(['article_path_option_id' => null, 'slug' => 'post', 'title' => '直した']))
            ->assertOk()
            ->assertJsonPath('data.path', '/old/post')
            ->assertJsonPath('data.title', '直した');
    }

    public function test_update_moves_article_to_another_path_option(): void
    {
        $news = ArticlePathOption::factory()->create(['parent_path' => 'news']);
        $article = Article::factory()->for($this->user)->create(['parent_path' => 'blog', 'slug' => 'post']);
        $this->actingAsUserWithToken();

        $this->putJson(route('api.me.articles.update', $article), $this->articleInput(['article_path_option_id' => $news->id, 'slug' => 'post']))
            ->assertOk()
            ->assertJsonPath('data.path', '/news/post');
    }

    public function test_update_of_published_article_returns_it_to_pending_and_keeps_draft_as_draft(): void
    {
        $published = Article::factory()->for($this->user)->published()->create();
        $draft = Article::factory()->for($this->user)->create();
        $this->actingAsUserWithToken();

        $this->putJson(route('api.me.articles.update', $published), $this->articleInput(['slug' => 'a']))->assertOk()->assertJsonPath('data.approval', 'pending');
        $this->putJson(route('api.me.articles.update', $draft), $this->articleInput(['slug' => 'b']))->assertOk()->assertJsonPath('data.approval', 'draft');
    }

    public function test_update_thumbnail_stores_resized_image_and_returns_published_article_to_pending(): void
    {
        Storage::fake('public');
        $article = Article::factory()->for($this->user)->published()->create();
        $this->actingAsUserWithToken();

        $this->post(route('api.me.articles.thumbnail', $article), ['thumbnail' => UploadedFile::fake()->image('thumb.jpg', 1600, 900)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.approval', 'pending');

        $article->refresh();
        $this->assertStringStartsWith(Article::THUMBNAIL_DIRECTORY.'/', $article->thumbnail);
        Storage::disk('public')->assertExists($article->thumbnail);
    }

    public function test_submit_and_withdraw_change_approval_with_audit_log(): void
    {
        $article = Article::factory()->for($this->user)->create(['review_comment' => '直してください']);
        $this->actingAsUserWithToken();

        $this->postJson(route('api.me.articles.submit', $article))
            ->assertOk()
            ->assertJsonPath('data.approval', 'pending')
            ->assertJsonPath('data.review_comment', null);

        $this->postJson(route('api.me.articles.withdraw', $article))
            ->assertOk()
            ->assertJsonPath('data.approval', 'draft');

        $this->assertSame(2, AuditLog::query()->where('subject_id', $article->id)->where('action', AuditAction::StatusChanged)->count());
    }

    public function test_submit_and_withdraw_reject_wrong_status(): void
    {
        $published = Article::factory()->for($this->user)->published()->create();
        $draft = Article::factory()->for($this->user)->create();
        $this->actingAsUserWithToken();

        $this->postJson(route('api.me.articles.submit', $published))->assertJsonValidationErrors('approval');
        $this->postJson(route('api.me.articles.withdraw', $draft))->assertJsonValidationErrors('approval');
        $this->assertSame(ArticleApprovalStatus::Published, $published->fresh()->approval);
    }

    public function test_destroy_soft_deletes_own_article_including_published(): void
    {
        $article = Article::factory()->for($this->user)->published()->create();
        $this->actingAsUserWithToken();

        $this->deleteJson(route('api.me.articles.destroy', $article))->assertNoContent();

        $this->assertSoftDeleted($article);
        $this->getJson(route('api.me.articles.show', $article))->assertNotFound();
    }

    public function test_upload_content_image_returns_url_under_content_directory(): void
    {
        Storage::fake('public');
        $this->actingAsUserWithToken();

        $response = $this->post(route('api.me.articles.content-images'), ['image' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json']);

        $response->assertOk();
        $this->assertStringStartsWith(Article::contentImageUrlPrefix(), $response->json('url'));
        $this->assertCount(1, Storage::disk('public')->files(Article::CONTENT_IMAGE_DIRECTORY));
    }

    public function test_search_tags_returns_matching_tags(): void
    {
        Tag::factory()->create(['tag_name' => 'Laravel']);
        Tag::factory()->create(['tag_name' => 'Vue']);
        $this->actingAsUserWithToken();

        $this->getJson(route('api.me.tags.search', ['q' => 'lara']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Laravel');
    }
}
