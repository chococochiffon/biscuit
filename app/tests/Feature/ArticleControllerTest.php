<?php

namespace Tests\Feature;

use App\Enums\ArticleApprovalStatus;
use App\Models\Administrator;
use App\Models\Article;
use App\Models\SinglePage;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArticleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_article_pages(): void
    {
        $response = $this->get(route('admin.articles.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_index_displays_articles(): void
    {
        $actor = Administrator::factory()->create();
        $user = User::factory()->create(['name' => 'Jane Doe']);
        $article = Article::factory()->for($user)->create(['title' => 'テスト記事']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index'));

        $response->assertOk();
        $response->assertSee($article->title);
        $response->assertSee('Jane Doe');
    }

    public function test_index_displays_columns_in_expected_order(): void
    {
        $actor = Administrator::factory()->create();
        Article::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['<table', 'サムネイル', 'タイトル', '公開開始', '公開終了', 'ステータス', '投稿者', '更新日時'], false);
    }

    public function test_index_collapses_search_form_when_not_searching(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index'));

        $response->assertSee('id="article-search-body" class="collapse"', false);
        $response->assertSee('aria-expanded="false"', false);
        $response->assertDontSee('検索中');
    }

    public function test_index_expands_search_form_while_searching(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index', ['title' => 'Laravel']));

        $response->assertSee('id="article-search-body" class="collapse show"', false);
        $response->assertSee('aria-expanded="true"', false);
        $response->assertSee('検索中');
    }

    public function test_index_keeps_search_form_collapsed_when_only_sort_is_specified(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index', ['sort' => 'title_asc']));

        $response->assertSee('id="article-search-body" class="collapse"', false);
    }

    public function test_index_displays_path(): void
    {
        $actor = Administrator::factory()->create();
        Article::factory()->create(['parent_path' => 'news', 'slug' => 'first-post']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index'));

        $response->assertSee('/news/first-post');
    }

    public function test_index_displays_search_fields_in_expected_order(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['id="search-title"', 'id="search-publication_start-from"', 'id="search-publication_end-from"', 'id="search-approval"'], false);
    }

    public function test_index_header_link_toggles_sort_direction(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index', ['sort' => 'title_asc', 'title' => 'Laravel']));

        $response->assertOk();
        // 並び中の項目は方向を反転し、検索条件は引き継ぐ
        $response->assertSee(e(route('admin.articles.index', ['sort' => 'title_desc', 'title' => 'Laravel'])), false);
        // 未選択の日時項目は新しい順から
        $response->assertSee(e(route('admin.articles.index', ['sort' => 'publication_start_desc', 'title' => 'Laravel'])), false);
    }

    public function test_index_orders_articles_by_updated_at_desc_by_default(): void
    {
        $actor = Administrator::factory()->create();
        $older = Article::factory()->create(['updated_at' => now()->subDay()]);
        $newer = Article::factory()->create(['updated_at' => now()]);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index'));

        $response->assertOk();
        $this->assertSame([$newer->id, $older->id], $response->viewData('articles')->pluck('id')->all());
    }

    public function test_index_can_sort_by_publication_start(): void
    {
        $actor = Administrator::factory()->create();
        $later = Article::factory()->create(['publication_start_datetime' => '2026-10-10 00:00:00']);
        $earlier = Article::factory()->create(['publication_start_datetime' => '2026-10-01 00:00:00']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index', ['sort' => 'publication_start_asc']));

        $this->assertSame([$earlier->id, $later->id], $response->viewData('articles')->pluck('id')->all());
    }

    public function test_index_ignores_unknown_sort_and_falls_back_to_default(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index', ['sort' => 'unknown', 'approval' => 'invalid']));

        $response->assertOk();
        $this->assertSame('updated_at_desc', $response->viewData('sort'));
    }

    public function test_index_searches_by_title_and_approval(): void
    {
        $actor = Administrator::factory()->create();
        $hit = Article::factory()->published()->create(['title' => 'Laravel入門']);
        Article::factory()->create(['title' => 'Laravel応用', 'approval' => ArticleApprovalStatus::Draft]);
        Article::factory()->published()->create(['title' => 'PHP入門']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index', [
            'title' => 'Laravel',
            'approval' => ArticleApprovalStatus::Published->value,
        ]));

        $this->assertSame([$hit->id], $response->viewData('articles')->pluck('id')->all());
    }

    public function test_index_searches_by_publication_period(): void
    {
        $actor = Administrator::factory()->create();
        $hit = Article::factory()->create([
            'publication_start_datetime' => '2026-10-05 10:00:00',
            'publication_end_datetime' => '2026-12-31 23:59:00',
        ]);
        Article::factory()->create([
            'publication_start_datetime' => '2026-09-01 10:00:00',
            'publication_end_datetime' => '2026-12-31 23:59:00',
        ]);
        Article::factory()->create([
            'publication_start_datetime' => '2026-10-05 10:00:00',
            'publication_end_datetime' => null,
        ]);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index', [
            'publication_start_from' => '2026-10-01',
            'publication_start_to' => '2026-10-31',
            'publication_end_from' => '2026-12-01',
            'publication_end_to' => '2026-12-31',
        ]));

        $this->assertSame([$hit->id], $response->viewData('articles')->pluck('id')->all());
    }

    public function test_index_displays_publication_datetimes(): void
    {
        $actor = Administrator::factory()->create();
        Article::factory()->create([
            'publication_start_datetime' => '2026-10-01 09:00:00',
            'publication_end_datetime' => '2026-10-31 23:59:30',
        ]);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index'));

        $response->assertOk();
        $response->assertSee('公開開始');
        $response->assertSee('公開終了');
        $response->assertSee('2026/10/01 09:00');
        $response->assertSee('2026/10/31 23:59');
        $response->assertDontSee('23:59:30');
    }

    public function test_index_displays_not_set_when_publication_end_datetime_is_null(): void
    {
        $actor = Administrator::factory()->create();
        Article::factory()->create([
            'publication_start_datetime' => '2026-10-01 09:00:00',
            'publication_end_datetime' => null,
        ]);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index'));

        $response->assertOk();
        $response->assertSee('未設定');
    }

    public function test_index_displays_administrator_for_null_user(): void
    {
        $actor = Administrator::factory()->create();
        Article::factory()->byAdministrator()->create(['title' => '管理者記事']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.index'));

        $response->assertOk();
        $response->assertSee('管理者');
    }

    public function test_create_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.create'));

        $response->assertOk();
    }

    public function test_store_creates_article_published_with_null_user_id(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.articles.store'), [
            'title' => '新しい記事',
            'content' => '<p>本文です</p>',
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
        ]);

        $response->assertRedirect(route('admin.articles.index'));

        $article = Article::where('title', '新しい記事')->firstOrFail();
        $this->assertNull($article->user_id);
        $this->assertSame(ArticleApprovalStatus::Published, $article->approval);
        $this->assertSame(Article::DEFAULT_THUMBNAIL_PATH, $article->thumbnail);
    }

    public function test_store_fails_validation_with_missing_fields(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.articles.store'), []);

        $response->assertSessionHasErrors(['title', 'content']);
    }

    public function test_store_uploads_thumbnail_with_expected_filename(): void
    {
        $this->freezeTime();
        Storage::fake('public');
        $actor = Administrator::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($actor, 'admin')->post(route('admin.articles.store'), [
            'title' => 'サムネイル記事',
            'content' => '<p>本文</p>',
            'thumbnail' => $file,
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
        ]);

        $response->assertRedirect(route('admin.articles.index'));

        $article = Article::where('title', 'サムネイル記事')->firstOrFail();
        $expectedPath = 'image/thumbnail/'.now()->format('YmdHis').'_articles_'.$article->id.'.jpg';
        $this->assertSame($expectedPath, $article->thumbnail);
        Storage::disk('public')->assertExists($expectedPath);
    }

    /**
     * @return array<string, array{int, int, int, int}>
     */
    public static function thumbnailSizeProvider(): array
    {
        return [
            '約1.91:1の大きな画像は1200×630に縮小' => [2400, 1260, 1200, 630],
            '16:9の大きな画像は1280×720に縮小' => [1600, 900, 1280, 720],
            '横長すぎる画像は1200×630に切り抜き' => [3000, 1000, 1200, 630],
            '正方形の画像は比率が近い1280×720に切り抜き' => [1000, 1000, 1280, 720],
            '小さな画像も1280×720に拡大' => [320, 180, 1280, 720],
        ];
    }

    #[DataProvider('thumbnailSizeProvider')]
    public function test_store_resizes_thumbnail_to_closest_aspect_ratio(int $sourceWidth, int $sourceHeight, int $expectedWidth, int $expectedHeight): void
    {
        Storage::fake('public');
        $actor = Administrator::factory()->create();

        $this->actingAs($actor, 'admin')->post(route('admin.articles.store'), [
            'title' => 'サムネイル記事',
            'content' => '<p>本文</p>',
            'thumbnail' => UploadedFile::fake()->image('photo.jpg', $sourceWidth, $sourceHeight),
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
        ])->assertRedirect(route('admin.articles.index'));

        $article = Article::where('title', 'サムネイル記事')->firstOrFail();
        [$width, $height] = getimagesize(Storage::disk('public')->path($article->thumbnail));

        $this->assertSame([$expectedWidth, $expectedHeight], [$width, $height]);
    }

    public function test_store_builds_path_from_parent_path_and_slug(): void
    {
        $actor = Administrator::factory()->create();

        $this->actingAs($actor, 'admin')->post(route('admin.articles.store'), $this->validArticlePayload(['parent_path' => '/news/', 'slug' => 'first-post']))
            ->assertRedirect(route('admin.articles.index'));

        $article = Article::where('slug', 'first-post')->firstOrFail();
        $this->assertSame('news', $article->parent_path);
        $this->assertSame('/news/first-post', $article->path);
    }

    public function test_store_uses_article_id_in_path_when_slug_is_empty(): void
    {
        $actor = Administrator::factory()->create();

        $this->actingAs($actor, 'admin')->post(route('admin.articles.store'), $this->validArticlePayload(['parent_path' => 'news', 'slug' => '']))
            ->assertRedirect(route('admin.articles.index'));

        $article = Article::where('title', 'URL記事')->firstOrFail();
        $this->assertNull($article->slug);
        $this->assertSame('/news/'.$article->id, $article->path);
    }

    public function test_update_switches_path_to_article_id_when_slug_is_cleared(): void
    {
        $actor = Administrator::factory()->create();
        $target = Article::factory()->create(['parent_path' => 'news', 'slug' => 'first-post']);

        $this->actingAs($actor, 'admin')->put(route('admin.articles.update', $target), $this->validArticlePayload([
            'parent_path' => 'news',
            'slug' => '',
            'approval' => $target->approval->value,
        ]))->assertRedirect(route('admin.articles.index'));

        $this->assertSame('/news/'.$target->id, $target->fresh()->path);
    }

    public function test_update_allows_keeping_its_own_path(): void
    {
        $actor = Administrator::factory()->create();
        $target = Article::factory()->create(['parent_path' => 'news', 'slug' => 'first-post']);

        $response = $this->actingAs($actor, 'admin')->put(route('admin.articles.update', $target), $this->validArticlePayload([
            'parent_path' => 'news',
            'slug' => 'first-post',
            'approval' => $target->approval->value,
        ]));

        $response->assertSessionHasNoErrors();
    }

    public function test_store_rejects_numeric_slug(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.articles.store'), $this->validArticlePayload(['parent_path' => 'news', 'slug' => '123']));

        $response->assertSessionHasErrors('slug');
    }

    public function test_store_rejects_path_used_by_another_article_or_single_page(): void
    {
        $actor = Administrator::factory()->create();
        Article::factory()->create(['parent_path' => 'news', 'slug' => 'first-post']);
        SinglePage::factory()->create(['parent_path' => 'company', 'slug' => 'about']);

        $this->actingAs($actor, 'admin')->post(route('admin.articles.store'), $this->validArticlePayload(['parent_path' => 'news', 'slug' => 'first-post']))
            ->assertSessionHasErrors('slug');
        $this->actingAs($actor, 'admin')->post(route('admin.articles.store'), $this->validArticlePayload(['parent_path' => 'company', 'slug' => 'about']))
            ->assertSessionHasErrors('slug');
    }

    public function test_create_screen_uses_parent_path_of_latest_article_as_default(): void
    {
        $actor = Administrator::factory()->create();
        Article::factory()->create(['parent_path' => 'blog']);
        Article::factory()->create(['parent_path' => 'news']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.create'));

        $response->assertSee('name="parent_path"', false);
        $response->assertSee('value="news"', false);
    }

    public function test_store_creates_new_tags_and_attaches_existing_ones(): void
    {
        $actor = Administrator::factory()->create();
        $existingTag = Tag::factory()->create(['tag_name' => 'Laravel']);

        $response = $this->actingAs($actor, 'admin')->post(route('admin.articles.store'), [
            'title' => 'タグ記事',
            'content' => '<p>本文</p>',
            'tags' => ['Laravel', 'NewTag'],
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
        ]);

        $response->assertRedirect(route('admin.articles.index'));

        $article = Article::where('title', 'タグ記事')->firstOrFail();
        $this->assertCount(2, $article->tags);
        $this->assertTrue($article->tags->contains($existingTag));
        $this->assertDatabaseHas('tags', ['tag_name' => 'NewTag']);
    }

    public function test_show_screen_does_not_exist(): void
    {
        $actor = Administrator::factory()->create();
        $target = Article::factory()->create();

        $this->assertFalse(Route::has('admin.articles.show'));
        $this->actingAs($actor, 'admin')->get('/admin/articles/'.$target->id)->assertMethodNotAllowed();
    }

    public function test_edit_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();
        $target = Article::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.edit', $target));

        $response->assertOk();
    }

    public function test_update_modifies_article_and_keeps_existing_user_id(): void
    {
        $actor = Administrator::factory()->create();
        $user = User::factory()->create();
        $target = Article::factory()->for($user)->create([
            'approval' => ArticleApprovalStatus::Draft,
        ]);

        $response = $this->actingAs($actor, 'admin')->put(route('admin.articles.update', $target), [
            'title' => '更新後のタイトル',
            'content' => '<p>更新後の本文</p>',
            'approval' => ArticleApprovalStatus::Published->value,
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
        ]);

        $response->assertRedirect(route('admin.articles.index'));

        $target->refresh();
        $this->assertSame('更新後のタイトル', $target->title);
        $this->assertSame(ArticleApprovalStatus::Published, $target->approval);
        $this->assertSame($user->id, $target->user_id);
    }

    public function test_update_replaces_thumbnail(): void
    {
        $this->freezeTime();
        Storage::fake('public');
        $actor = Administrator::factory()->create();
        $target = Article::factory()->create();
        $file = UploadedFile::fake()->image('new.png', 1600, 900);

        $response = $this->actingAs($actor, 'admin')->put(route('admin.articles.update', $target), [
            'title' => $target->title,
            'content' => $target->content,
            'approval' => $target->approval->value,
            'thumbnail' => $file,
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
        ]);

        $response->assertRedirect(route('admin.articles.index'));

        $expectedPath = 'image/thumbnail/'.now()->format('YmdHis').'_articles_'.$target->id.'.png';
        $this->assertSame($expectedPath, $target->fresh()->thumbnail);
        Storage::disk('public')->assertExists($expectedPath);
        $this->assertSame([1280, 720], array_slice(getimagesize(Storage::disk('public')->path($expectedPath)), 0, 2));
    }

    public function test_update_syncs_tags(): void
    {
        $actor = Administrator::factory()->create();
        $target = Article::factory()->create();
        $keptTag = Tag::factory()->create(['tag_name' => 'Kept']);
        $removedTag = Tag::factory()->create(['tag_name' => 'Removed']);
        $target->tags()->sync([$keptTag->id, $removedTag->id]);

        $response = $this->actingAs($actor, 'admin')->put(route('admin.articles.update', $target), [
            'title' => $target->title,
            'content' => $target->content,
            'approval' => $target->approval->value,
            'tags' => ['Kept', 'Added'],
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
        ]);

        $response->assertRedirect(route('admin.articles.index'));

        $tagNames = $target->fresh()->tags->pluck('tag_name')->sort()->values()->all();
        $this->assertSame(['Added', 'Kept'], $tagNames);
    }

    public function test_store_persists_publication_start_and_end_datetimes(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.articles.store'), [
            'title' => '公開期間付き記事',
            'content' => '<p>本文</p>',
            'publication_start_datetime' => '2026-10-01 09:00',
            'publication_end_datetime' => '2026-10-31 23:59',
        ]);

        $response->assertRedirect(route('admin.articles.index'));

        $article = Article::where('title', '公開期間付き記事')->firstOrFail();
        $this->assertSame('2026-10-01 09:00', $article->publication_start_datetime->format('Y-m-d H:i'));
        $this->assertSame('2026-10-31 23:59', $article->publication_end_datetime->format('Y-m-d H:i'));
    }

    public function test_store_fails_validation_without_publication_start_datetime(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.articles.store'), [
            'title' => '公開開始日時なし記事',
            'content' => '<p>本文</p>',
        ]);

        $response->assertSessionHasErrors(['publication_start_datetime']);
    }

    public function test_store_fails_validation_when_publication_end_datetime_is_before_start(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.articles.store'), [
            'title' => '公開期間逆転記事',
            'content' => '<p>本文</p>',
            'publication_start_datetime' => '2026-10-10 00:00',
            'publication_end_datetime' => '2026-10-01 00:00',
        ]);

        $response->assertSessionHasErrors(['publication_end_datetime']);
    }

    public function test_guests_are_redirected_from_approval_update(): void
    {
        $target = Article::factory()->create();

        $response = $this->patch(route('admin.articles.approval', $target), [
            'approval' => ArticleApprovalStatus::Published->value,
        ]);

        $response->assertRedirect(route('admin.login'));
    }

    public function test_approval_update_changes_only_the_approval_of_the_target_article(): void
    {
        $actor = Administrator::factory()->create();
        $target = Article::factory()->create(['approval' => ArticleApprovalStatus::Draft, 'title' => '対象記事']);
        $other = Article::factory()->create(['approval' => ArticleApprovalStatus::Draft]);

        $response = $this->actingAs($actor, 'admin')->patch(route('admin.articles.approval', $target), [
            'approval' => ArticleApprovalStatus::Published->value,
        ]);

        $response->assertRedirect();
        $this->assertSame(ArticleApprovalStatus::Published, $target->fresh()->approval);
        $this->assertSame(ArticleApprovalStatus::Draft, $other->fresh()->approval);
        $this->assertSame('対象記事', $target->fresh()->title);
    }

    public function test_bulk_approval_update_changes_only_the_selected_articles(): void
    {
        $actor = Administrator::factory()->create();
        $selected = Article::factory()->count(2)->create(['approval' => ArticleApprovalStatus::Draft]);
        $notSelected = Article::factory()->create(['approval' => ArticleApprovalStatus::Draft]);

        $response = $this->actingAs($actor, 'admin')->patch(route('admin.articles.bulk-approval'), [
            'article_ids' => $selected->pluck('id')->all(),
            'approval' => ArticleApprovalStatus::Published->value,
        ]);

        $response->assertRedirect();
        foreach ($selected as $article) {
            $this->assertSame(ArticleApprovalStatus::Published, $article->fresh()->approval);
        }
        $this->assertSame(ArticleApprovalStatus::Draft, $notSelected->fresh()->approval);
    }

    public function test_bulk_approval_update_fails_validation_without_article_ids(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->patch(route('admin.articles.bulk-approval'), [
            'approval' => ArticleApprovalStatus::Published->value,
        ]);

        $response->assertSessionHasErrors(['article_ids']);
    }

    public function test_destroy_deletes_article(): void
    {
        $actor = Administrator::factory()->create();
        $target = Article::factory()->create();

        $response = $this->actingAs($actor, 'admin')->delete(route('admin.articles.destroy', $target));

        $response->assertRedirect(route('admin.articles.index'));
        $this->assertSoftDeleted('articles', ['id' => $target->id]);
    }

    public function test_content_image_upload_stores_file_and_returns_url(): void
    {
        Storage::fake('public');
        $actor = Administrator::factory()->create();
        $file = UploadedFile::fake()->image('inline.jpg');

        $response = $this->actingAs($actor, 'admin')->postJson(route('admin.articles.content-images'), [
            'image' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['url']);

        $url = $response->json('url');
        $this->assertStringContainsString('/storage/image/content/', $url);
    }

    /**
     * 記事の登録・更新で必須項目を満たす入力値。
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validArticlePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'URL記事',
            'content' => '<p>本文</p>',
            'publication_start_datetime' => now()->format('Y-m-d H:i'),
        ], $overrides);
    }
}
