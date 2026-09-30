<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Administrator;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\GalleryImage;
use App\Models\PageView;
use App\Models\SinglePage;
use App\Models\TopSliderImage;
use App\Models\User;
use App\Services\MediaStatsService;
use App\Services\SystemStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_dashboard_is_displayed(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('ダッシュボード');
    }

    public function test_dashboard_shows_page_view_summary(): void
    {
        $this->actingAsAdmin();
        $this->travelTo('2026-10-15 12:00:00');
        PageView::factory()->count(3)->create(['viewed_at' => now()]);
        PageView::factory()->create(['viewed_at' => now()->subDay()]);
        PageView::factory()->create(['viewed_at' => now()->subMonth()]);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary) => $summary['today']['views'] === 3
            && $summary['yesterday']['views'] === 1
            && $summary['this_month']['views'] === 4
            && $summary['total']['views'] === 5);
        $response->assertSee(route('admin.page-views.index'));
    }

    public function test_dashboard_shows_content_counts(): void
    {
        $this->actingAsAdmin();
        $this->travelTo('2026-10-15 12:00:00');
        Article::factory()->published()->count(2)->create(['publication_start_datetime' => now()->subDay()]);
        Article::factory()->published()->create(['publication_start_datetime' => now()->addDay()]);
        Article::factory()->published()->create(['publication_start_datetime' => now()->subWeek(), 'publication_end_datetime' => now()->subDay()]);
        Article::factory()->count(3)->create();
        Article::factory()->pending()->create();
        SinglePage::factory()->create(['publication_start_datetime' => now()->subDay()]);
        SinglePage::factory()->create(['publication_start_datetime' => now()->addDay()]);
        SinglePage::factory()->create(['publication_start_datetime' => now()->subWeek(), 'publication_end_datetime' => now()->subDay()]);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('counts', [
            'article' => ['total' => 8, 'published' => 2, 'scheduled' => 1, 'draft' => 3, 'pending' => 1, 'unpublished' => 5],
            'single_page' => ['total' => 3, 'published' => 1, 'scheduled' => 1, 'unpublished' => 1],
        ]);
    }

    public function test_dashboard_shows_recently_edited_contents_with_updater(): void
    {
        $this->actingAsAdmin();
        $this->travelTo('2026-10-15 12:00:00');
        $older = Article::factory()->create(['title' => '古い記事', 'updated_at' => now()->subDays(2)]);
        $article = Article::factory()->create(['title' => '新しい記事', 'updated_at' => now()->subHour()]);
        $page = SinglePage::factory()->create(['title' => '会社概要', 'updated_at' => now()->subDay()]);
        $this->recordLog('編集者A', 'article', $article->id);
        $this->recordLog('編集者B', 'article', $article->id);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('recentContents', fn ($rows) => $rows->pluck('title')->all() === ['新しい記事', '会社概要', '古い記事']
            && $rows[0]['updated_by'] === '編集者B'
            && $rows[1]['updated_by'] === null);
        $response->assertSee(route('admin.articles.edit', $article));
        $response->assertSee(route('admin.single-pages.edit', $page));
        $response->assertSee(route('admin.articles.edit', $older));
    }

    public function test_dashboard_shows_scheduled_contents_for_today_and_this_week(): void
    {
        $this->actingAsAdmin();
        $this->travelTo('2026-10-15 12:00:00');
        Article::factory()->published()->create(['title' => '今日の記事', 'publication_start_datetime' => '2026-10-15 18:00:00']);
        Article::factory()->pending()->create(['title' => '未承認の記事', 'publication_start_datetime' => '2026-10-15 18:00:00']);
        SinglePage::factory()->create(['title' => '今週のページ', 'publication_start_datetime' => '2026-10-20 09:00:00']);
        Article::factory()->published()->create(['title' => '来月の記事', 'publication_start_datetime' => '2026-11-15 09:00:00']);
        Article::factory()->published()->create(['title' => '公開済みの記事', 'publication_start_datetime' => '2026-10-15 09:00:00']);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('scheduled', fn (array $scheduled) => $scheduled['today']->pluck('title')->all() === ['今日の記事']
            && $scheduled['this_week']->pluck('title')->all() === ['今週のページ']);
    }

    public function test_dashboard_shows_content_warnings(): void
    {
        $this->actingAsAdmin();
        $this->travelTo('2026-10-15 12:00:00');
        $noThumbnail = Article::factory()->published()->create(['title' => 'サムネイルなし', 'thumbnail' => Article::DEFAULT_THUMBNAIL_PATH, 'publication_start_datetime' => now()->subDay()]);
        Article::factory()->published()->create(['thumbnail' => 'image/thumbnail/a.jpg', 'publication_start_datetime' => now()->subDay()]);
        Article::factory()->create(['thumbnail' => null]);
        Article::factory()->published()->create(['thumbnail' => null, 'publication_start_datetime' => now()->subWeek(), 'publication_end_datetime' => now()->subDay()]);
        $noSummary = SinglePage::factory()->create(['title' => '短文なし', 'short_sentences' => '', 'publication_start_datetime' => now()->addDay()]);
        Article::factory()->pending()->create(['thumbnail' => 'image/thumbnail/b.jpg']);
        GalleryImage::factory()->pending()->count(2)->create();

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('warnings', fn (array $warnings) => array_column($warnings, 'count') === [1, 1, 1, 2]
            && $warnings[0]['items']->pluck('title')->all() === ['サムネイルなし']
            && $warnings[1]['items']->pluck('title')->all() === ['短文なし']);
        $response->assertSee(route('admin.articles.edit', $noThumbnail));
        $response->assertSee(route('admin.single-pages.edit', $noSummary));
    }

    public function test_dashboard_shows_no_warnings_when_contents_are_fine(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('warnings', [])
            ->assertSee('気になる点はありません。');
    }

    public function test_super_admin_sees_everyones_recent_operations(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $other = Administrator::factory()->create();
        $this->recordLog($admin->name, 'article', 1, $admin);
        $this->recordLog($other->name, 'article', 2, $other);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('auditLogs', fn ($logs) => $logs->count() === 2)
            ->assertSee(route('admin.audit-logs.index'));
    }

    public function test_admin_sees_only_own_recent_operations(): void
    {
        $admin = $this->actingAsAdmin();
        $other = Administrator::factory()->create();
        $this->recordLog($admin->name, 'article', 1, $admin);
        $this->recordLog($other->name, 'article', 2, $other);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('auditLogs', fn ($logs) => $logs->pluck('actor_id')->all() === [$admin->id])
            ->assertDontSee(route('admin.audit-logs.index'));
    }

    public function test_dashboard_shows_user_status(): void
    {
        $this->actingAsAdmin();
        User::factory()->count(2)->create();
        User::factory()->skipsApproval()->create();
        User::factory()->invited()->create();
        Administrator::factory()->superAdmin()->create();
        $user = User::factory()->create(['name' => 'ログインしたユーザー']);
        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'action' => AuditAction::Login,
        ]);

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('userStatus', fn (array $status) => $status['users'] === ['total' => 5, 'active' => 4, 'invited' => 1, 'skip_approval' => 1]
            && $status['administrators']['admin']['count'] === 1
            && $status['administrators']['super_admin']['count'] === 1
            && $status['recent_logins']->pluck('actor_name')->all() === ['ログインしたユーザー']);
    }

    public function test_dashboard_shows_media_status_with_unused_images(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        Storage::disk('public')->put('image/thumbnail/used.png', str_repeat('a', 100));
        Storage::disk('public')->put('image/content/in-content.png', str_repeat('b', 50));
        Storage::disk('public')->put('image/top_image/deleted-slider.png', str_repeat('c', 10));
        Storage::disk('public')->put('image/gallery/unused.png', str_repeat('d', 30));
        Storage::disk('public')->put(Article::DEFAULT_THUMBNAIL_PATH, 'default');
        Article::factory()->create([
            'thumbnail' => 'image/thumbnail/used.png',
            'content' => '<p><img src="http://localhost/storage/image/content/in-content.png"></p>',
        ]);
        TopSliderImage::factory()->create(['top_image' => 'image/top_image/deleted-slider.png'])->delete();

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('media', fn (array $media) => $media['total_count'] === 4
            && $media['total_bytes'] === 190
            && $media['unused_count'] === 1
            && $media['unused_bytes'] === 30
            && $media['unused_items'] === ['image/gallery/unused.png']);
        $response->assertSee('image/gallery/unused.png');
    }

    public function test_media_status_is_cached(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $this->get(route('admin.dashboard'))->assertViewHas('media', fn (array $media) => $media['total_count'] === 0);

        Storage::disk('public')->put('image/gallery/new.png', 'x');

        $this->get(route('admin.dashboard'))->assertViewHas('media', fn (array $media) => $media['total_count'] === 0);
        cache()->forget(MediaStatsService::CACHE_KEY);
        $this->get(route('admin.dashboard'))->assertViewHas('media', fn (array $media) => $media['total_count'] === 1);
    }

    public function test_super_admin_sees_system_info_without_warnings_when_healthy(): void
    {
        $this->actingAsSuperAdmin();
        $this->useLogDirectory();

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('systemStatus', fn (array $status) => $status['info']['biscuit_version'] === config('biscuit.version')
            && $status['info']['php_version'] === PHP_VERSION
            && $status['errors']['count'] === 0
            && $status['warnings'] === []);
        $response->assertSee('Biscuit '.config('biscuit.version'));
        $response->assertDontSee('data-system-warnings', false);
    }

    public function test_super_admin_sees_recent_errors_as_a_warning(): void
    {
        $this->actingAsSuperAdmin();
        $this->travelTo('2026-10-15 12:00:00');
        $directory = $this->useLogDirectory();
        File::put($directory.'/laravel.log', implode("\n", [
            '[2026-10-13 12:00:00] local.ERROR: 古いエラー',
            '[2026-10-15 09:00:00] local.INFO: 情報',
            '[2026-10-15 10:00:00] local.ERROR: 新しいエラー {"exception":"..."}',
            '#0 stack trace',
            '[2026-10-15 11:00:00] local.CRITICAL: 最後のエラー',
        ]));
        touch($directory.'/laravel.log', now()->getTimestamp());

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('systemStatus', fn (array $status) => $status['errors']['count'] === 2
            && $status['errors']['last_message'] === '最後のエラー'
            && collect($status['warnings'])->contains('level', 'danger'));
        $response->assertSee('data-system-warnings', false);
        $response->assertSee('最後のエラー');
    }

    public function test_super_admin_sees_debug_mode_warning_in_production(): void
    {
        $this->actingAsSuperAdmin();
        $this->useLogDirectory();
        config(['app.debug' => true]);
        $this->app['env'] = 'production';

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('本番環境でデバッグモード(APP_DEBUG)が有効です。');
    }

    public function test_admin_does_not_see_system_status(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('systemStatus', null)
            ->assertDontSee('data-system-info', false);
    }

    /**
     * システム情報が読むログのディレクトリを、テスト用の空のディレクトリに差し替える。
     */
    private function useLogDirectory(): string
    {
        $directory = storage_path('framework/testing/logs-'.uniqid());
        File::ensureDirectoryExists($directory);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($directory));
        $this->app->instance(SystemStatusService::class, new SystemStatusService($directory));

        return $directory;
    }

    /**
     * 操作ログを 1 件記録する(記録順に id が増える)。
     */
    private function recordLog(string $actorName, string $subjectType, int $subjectId, ?Administrator $actor = null): AuditLog
    {
        return AuditLog::create([
            'actor_type' => $actor ? 'administrator' : null,
            'actor_id' => $actor?->id,
            'actor_name' => $actorName,
            'action' => AuditAction::Updated,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => 'label',
        ]);
    }
}
