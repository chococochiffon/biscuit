<?php

namespace Tests\Feature\API;

use App\Enums\AuditAction;
use App\Http\Controllers\API\AuthController;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\GalleryImage;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * マイページのダッシュボードの、自分の記事・ギャラリーの状況など(GET /api/me/dashboard)。
 */
class MyDashboardControllerTest extends TestCase
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
        $this->getJson(route('api.me.dashboard.show'))->assertUnauthorized();
    }

    public function test_counts_only_own_contents_by_status(): void
    {
        Article::factory()->for($this->user)->published()->count(2)->create(['publication_start_datetime' => now()->subDay()]);
        Article::factory()->for($this->user)->published()->create(['publication_start_datetime' => now()->addDay()]);
        Article::factory()->for($this->user)->published()->create(['publication_start_datetime' => now()->subWeek(), 'publication_end_datetime' => now()->subDay()]);
        Article::factory()->for($this->user)->count(2)->create();
        Article::factory()->for($this->user)->pending()->create();
        Article::factory()->published()->create(['publication_start_datetime' => now()->subDay()]);
        GalleryImage::factory()->byUser($this->user)->published()->create();
        GalleryImage::factory()->byUser($this->user)->pending()->count(2)->create();
        GalleryImage::factory()->byUser($this->user)->create(['approval' => 'draft']);
        GalleryImage::factory()->create();
        $this->actingAsUserWithToken();

        $this->getJson(route('api.me.dashboard.show'))
            ->assertOk()
            ->assertJsonPath('data.counts', [
                'articles' => ['total' => 7, 'published' => 2, 'scheduled' => 1, 'draft' => 2, 'pending' => 1, 'unpublished' => 4],
                'gallery_images' => ['total' => 4, 'published' => 1, 'draft' => 1, 'pending' => 2],
            ]);
    }

    public function test_returns_recently_edited_own_contents(): void
    {
        $article = Article::factory()->for($this->user)->published()->create([
            'title' => '予約の記事',
            'publication_start_datetime' => now()->addDay(),
            'updated_at' => now()->subHour(),
        ]);
        $image = GalleryImage::factory()->byUser($this->user)->pending()->create(['name' => '写真', 'updated_at' => now()->subMinutes(5)]);
        Article::factory()->create(['title' => 'ほかの人の記事']);
        $this->actingAsUserWithToken();

        $this->getJson(route('api.me.dashboard.show'))
            ->assertOk()
            ->assertJsonPath('data.recent_contents', [
                ['type' => 'gallery_image', 'id' => $image->id, 'title' => '写真', 'status' => 'pending', 'updated_at' => now()->subMinutes(5)->toIso8601String()],
                ['type' => 'article', 'id' => $article->id, 'title' => '予約の記事', 'status' => 'scheduled', 'updated_at' => now()->subHour()->toIso8601String()],
            ]);
    }

    public function test_returns_own_scheduled_articles_for_today_and_this_week(): void
    {
        Article::factory()->for($this->user)->published()->create(['title' => '今日', 'publication_start_datetime' => '2026-10-15 18:00:00']);
        Article::factory()->for($this->user)->published()->create(['title' => '今週', 'publication_start_datetime' => '2026-10-20 09:00:00']);
        Article::factory()->for($this->user)->published()->create(['title' => '来月', 'publication_start_datetime' => '2026-11-20 09:00:00']);
        Article::factory()->for($this->user)->pending()->create(['title' => '承認待ち', 'publication_start_datetime' => '2026-10-15 18:00:00']);
        Article::factory()->published()->create(['title' => 'ほかの人', 'publication_start_datetime' => '2026-10-15 18:00:00']);
        $this->actingAsUserWithToken();

        $response = $this->getJson(route('api.me.dashboard.show'))->assertOk();

        $this->assertSame(['今日'], array_column($response->json('data.scheduled.today'), 'title'));
        $this->assertSame(['今週'], array_column($response->json('data.scheduled.this_week'), 'title'));
    }

    public function test_returns_warnings_for_returned_no_thumbnail_and_pending_contents(): void
    {
        $returned = Article::factory()->for($this->user)->create(['title' => '差し戻し', 'review_comment' => '画像を差し替えてください']);
        Article::factory()->for($this->user)->create(['review_comment' => null]);
        $returnedImage = GalleryImage::factory()->byUser($this->user)->create(['approval' => 'draft', 'name' => '差し戻しの写真', 'review_comment' => 'ぼやけています']);
        $noThumbnail = Article::factory()->for($this->user)->published()->create(['title' => 'サムネイルなし', 'thumbnail' => null, 'publication_start_datetime' => now()->subDay()]);
        Article::factory()->for($this->user)->pending()->create(['title' => '承認待ち', 'thumbnail' => 'image/thumbnail/a.png']);
        Article::factory()->create(['review_comment' => 'ほかの人']);
        $this->actingAsUserWithToken();

        $response = $this->getJson(route('api.me.dashboard.show'))->assertOk();

        $warnings = collect($response->json('data.warnings'))->keyBy('key');
        $this->assertSame(['returned', 'no_thumbnail', 'pending'], $warnings->keys()->all());
        $this->assertSame(2, $warnings['returned']['count']);
        $this->assertSame([
            ['type' => 'article', 'id' => $returned->id, 'title' => '差し戻し'],
            ['type' => 'gallery_image', 'id' => $returnedImage->id, 'title' => '差し戻しの写真'],
        ], $warnings['returned']['items']);
        $this->assertSame([['type' => 'article', 'id' => $noThumbnail->id, 'title' => 'サムネイルなし']], $warnings['no_thumbnail']['items']);
        $this->assertSame(1, $warnings['pending']['count']);
    }

    public function test_returns_own_articles_with_broken_links(): void
    {
        $article = Article::factory()->for($this->user)->create(['title' => 'リンク切れ', 'content' => '<p><a href="/missing">x</a></p>']);
        Article::factory()->for($this->user)->create(['content' => '<a href="https://example.com">外部</a>']);
        Article::factory()->create(['content' => '<a href="/missing">ほかの人</a>']);
        $this->actingAsUserWithToken();

        $this->getJson(route('api.me.dashboard.show'))
            ->assertOk()
            ->assertJsonPath('data.warnings', [[
                'key' => 'broken_links',
                'count' => 1,
                'items' => [['type' => 'article', 'id' => $article->id, 'title' => 'リンク切れ', 'links' => ['/missing']]],
            ]]);
    }

    public function test_returns_no_warnings_when_nothing_needs_attention(): void
    {
        $this->actingAsUserWithToken();

        $this->getJson(route('api.me.dashboard.show'))
            ->assertOk()
            ->assertJsonPath('data.warnings', []);
    }

    public function test_returns_only_own_recent_activities_and_logins(): void
    {
        $this->recordLog($this->user, AuditAction::Login, now()->subDay());
        $this->recordLog($this->user, AuditAction::LoginCodeSent, now()->subDay());
        $this->recordLog($this->user, AuditAction::Updated, now()->subHour(), 'article', '自分の記事');
        $this->recordLog(User::factory()->create(), AuditAction::Updated, now(), 'article', 'ほかの人の記事');
        $this->actingAsUserWithToken();

        $response = $this->getJson(route('api.me.dashboard.show'))->assertOk();

        $this->assertSame(['updated'], array_column($response->json('data.recent_activities'), 'action'));
        $response->assertJsonPath('data.recent_activities.0.subject_label', '自分の記事');
        $response->assertJsonPath('data.recent_activities.0.subject_type_label', '記事');
        $response->assertJsonMissingPath('data.recent_activities.0.ip_address');
        $response->assertJsonPath('data.account.recent_logins', [now()->subDay()->toIso8601String()]);
    }

    public function test_returns_account_status(): void
    {
        $this->user->update(['skip_approval' => true]);
        UserDetail::factory()->for($this->user)->create(['view_flag' => true]);
        $this->actingAsUserWithToken();

        $this->getJson(route('api.me.dashboard.show'))
            ->assertOk()
            ->assertJsonPath('data.account.skip_approval', true)
            ->assertJsonPath('data.account.public_profile', true)
            ->assertJsonPath('data.account.profile_path', '/authors/'.$this->user->id);
    }

    public function test_returns_own_uploaded_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('image/thumbnail/mine.png', str_repeat('a', 100));
        Storage::disk('public')->put('image/gallery/mine.png', str_repeat('b', 40));
        Storage::disk('public')->put('image/gallery/others.png', str_repeat('c', 10));
        Storage::disk('public')->put(Article::DEFAULT_THUMBNAIL_PATH, 'default');
        Article::factory()->for($this->user)->create(['thumbnail' => 'image/thumbnail/mine.png']);
        Article::factory()->for($this->user)->create(['thumbnail' => Article::DEFAULT_THUMBNAIL_PATH]);
        GalleryImage::factory()->byUser($this->user)->create(['image' => 'image/gallery/mine.png']);
        GalleryImage::factory()->create(['image' => 'image/gallery/others.png']);
        $this->actingAsUserWithToken();

        $this->getJson(route('api.me.dashboard.show'))
            ->assertOk()
            ->assertJsonPath('data.media.count', 2)
            ->assertJsonPath('data.media.bytes', 140)
            ->assertJsonPath('data.media.groups', [
                ['key' => 'thumbnail', 'count' => 1, 'bytes' => 100],
                ['key' => 'gallery', 'count' => 1, 'bytes' => 40],
                ['key' => 'icon', 'count' => 0, 'bytes' => 0],
            ]);
    }

    /**
     * ユーザーを操作者とする操作ログを 1 件記録する。
     */
    private function recordLog(User $user, AuditAction $action, \DateTimeInterface $at, ?string $subjectType = null, ?string $subjectLabel = null): void
    {
        $this->travelTo($at);
        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectType ? 1 : null,
            'subject_label' => $subjectLabel,
            'ip_address' => '127.0.0.1',
        ]);
        $this->travelTo('2026-10-15 12:00:00');
    }
}
