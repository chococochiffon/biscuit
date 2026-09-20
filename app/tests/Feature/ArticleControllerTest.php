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
        ]);

        $response->assertRedirect(route('admin.articles.index'));

        $tagNames = $target->fresh()->tags->pluck('tag_name')->sort()->values()->all();
        $this->assertSame(['Added', 'Kept'], $tagNames);
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
