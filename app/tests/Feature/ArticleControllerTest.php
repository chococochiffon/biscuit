<?php

namespace Tests\Feature;

use App\Enums\ArticleApprovalStatus;
use App\Models\Administrator;
use App\Models\Article;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $response->assertSeeInOrder(['<th>サムネイル</th>', '<th>タイトル</th>', '<th>公開開始</th>', '<th>公開終了</th>', '<th>ステータス</th>', '<th>投稿者</th>', '<th>更新日時</th>'], false);
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

    public function test_show_displays_article(): void
    {
        $actor = Administrator::factory()->create();
        $target = Article::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.articles.show', $target));

        $response->assertOk();
        $response->assertSee($target->title);
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
        $file = UploadedFile::fake()->image('new.png');

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
}
