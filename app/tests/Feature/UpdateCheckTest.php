<?php

namespace Tests\Feature;

use App\Enums\AdministratorRole;
use App\Models\Administrator;
use App\Notifications\UpdateAvailableNotification;
use App\Services\UpdateCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UpdateCheckTest extends TestCase
{
    use RefreshDatabase;

    private const RELEASE_URL = 'https://api.github.com/repos/chococochiffon/biscuit/releases/latest';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['biscuit.version' => '1.0.0', 'biscuit.update_check.enabled' => true, 'biscuit.update_check.repository' => 'chococochiffon/biscuit']);
        // 本番のキャッシュ(database)と同じく、値を serialize してオブジェクトを復元しない形で確かめる
        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');
    }

    /**
     * GitHub の最新のリリースの応答を、渡したタグの順に返す(同じ URL への 2 回目以降の問い合わせは次のタグ)。
     */
    private function fakeReleases(string ...$tags): void
    {
        $sequence = Http::fakeSequence(self::RELEASE_URL);

        foreach ($tags as $tag) {
            $sequence->push([
                'tag_name' => $tag,
                'name' => "Biscuit {$tag}",
                'html_url' => "https://github.com/chococochiffon/biscuit/releases/tag/{$tag}",
                'published_at' => '2026-10-01T03:00:00Z',
                'body' => "## 変更点\n- ページビルダーの改善",
            ]);
        }
    }

    public function test_a_newer_release_is_available_and_cached(): void
    {
        $this->fakeReleases('v1.2.0');
        $updates = app(UpdateCheckService::class);

        $update = $updates->availableUpdate();

        $this->assertSame('1.2.0', $update['version']);
        $this->assertSame('https://github.com/chococochiffon/biscuit/releases/tag/v1.2.0', $update['url']);
        $this->assertSame('2026-10-01 12:00', $update['published_at']->format('Y-m-d H:i'));
        $this->assertStringContainsString('ページビルダーの改善', $update['notes']);

        // 2 回目はキャッシュを使い、問い合わせない
        $updates->availableUpdate();
        Http::assertSentCount(1);
    }

    public function test_the_same_or_older_release_is_not_an_update(): void
    {
        $this->fakeReleases('v1.0.0', '0.9.9');
        $this->assertNull(app(UpdateCheckService::class)->availableUpdate());

        Cache::flush();
        $this->assertNull(app(UpdateCheckService::class)->availableUpdate());
        Http::assertSentCount(2);
    }

    public function test_no_release_is_not_an_update(): void
    {
        $updates = app(UpdateCheckService::class);

        Http::fake([self::RELEASE_URL => Http::response(['message' => 'Not Found'], 404)]);
        $this->assertNull($updates->availableUpdate());

        $this->assertNull($updates->availableUpdate());
    }

    public function test_invalid_tags_and_failures_are_not_updates(): void
    {
        $updates = app(UpdateCheckService::class);
        Http::fakeSequence(self::RELEASE_URL)->push(['tag_name' => 'latest'])->push('error', 500);

        $this->assertNull($updates->availableUpdate());

        Cache::flush();
        $this->assertNull($updates->availableUpdate());
        // 失敗もしばらくキャッシュし、問い合わせ直さない
        $this->assertNull($updates->availableUpdate());
        Http::assertSentCount(2);
    }

    public function test_nothing_is_requested_when_disabled_or_only_the_cache_is_read(): void
    {
        Http::fake();

        config(['biscuit.update_check.enabled' => false]);
        $this->assertNull(app(UpdateCheckService::class)->availableUpdate());

        config(['biscuit.update_check.enabled' => true]);
        $this->assertNull(app(UpdateCheckService::class)->availableUpdate(fetch: false));

        Http::assertNothingSent();
    }

    public function test_dashboard_and_every_admin_page_show_the_update_only_to_super_admins(): void
    {
        $this->fakeReleases('v1.2.0');

        $this->actingAsSuperAdmin();
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-update-available', false)
            ->assertSee('最新 1.2.0')
            ->assertSee('./biscuit update');
        // ダッシュボードが作ったキャッシュで、ほかの画面の上部にも出す
        $this->get(route('admin.builder-components.index'))->assertOk()->assertSee('data-update-notice', false)->assertSee('data-dismissible="update:1.2.0"', false);

        $this->actingAsAdmin(['role' => AdministratorRole::Admin]);
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('data-update-available', false);
        $this->get(route('admin.builder-components.index'))->assertOk()->assertDontSee('data-update-notice', false);
    }

    public function test_command_emails_super_admins_once_per_version(): void
    {
        Notification::fake();
        $superAdmin = Administrator::factory()->create(['role' => AdministratorRole::SuperAdmin]);
        $admin = Administrator::factory()->create(['role' => AdministratorRole::Admin]);
        $this->fakeReleases('v1.2.0', 'v1.2.0', 'v1.3.0');

        $this->artisan('biscuit:check-update')->assertSuccessful();
        $this->artisan('biscuit:check-update')->assertSuccessful();

        Notification::assertSentToTimes($superAdmin, UpdateAvailableNotification::class, 1);
        Notification::assertNotSentTo($admin, UpdateAvailableNotification::class);

        // 新しいバージョンが出たら、また知らせる
        $this->artisan('biscuit:check-update')->assertSuccessful();
        Notification::assertSentToTimes($superAdmin, UpdateAvailableNotification::class, 2);
    }

    public function test_update_email_contains_the_versions_and_release_link(): void
    {
        $this->fakeReleases('v1.2.0');
        $release = app(UpdateCheckService::class)->availableUpdate();

        $mail = (new UpdateAvailableNotification($release))->toMail(Administrator::factory()->create());
        $html = (string) $mail->render();

        $this->assertStringContainsString('1.2.0', $mail->subject);
        $this->assertStringContainsString('https://github.com/chococochiffon/biscuit/releases/tag/v1.2.0', $html);
        $this->assertStringContainsString('ページビルダーの改善', $html);
        $this->assertStringContainsString('./biscuit update', $html);
    }

    public function test_the_check_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('biscuit:check-update')->assertSuccessful();
    }
}
