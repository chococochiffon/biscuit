<?php

namespace Tests\Feature;

use App\Enums\AdministratorRole;
use App\Models\Administrator;
use App\Notifications\AnnouncementNotification;
use App\Services\AnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://raw.githubusercontent.com/chococochiffon/biscuit/main/announcements.json';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-10-10 12:00:00');
        config(['biscuit.version' => '1.2.0', 'biscuit.announcements.enabled' => true, 'biscuit.announcements.url' => self::URL]);
        // 本番のキャッシュ(database)と同じく、値を serialize してオブジェクトを復元しない形で確かめる
        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');
    }

    /**
     * @param  list<array<string, mixed>>  ...$files  読むたびに返すファイルの中身(順に)
     */
    private function fakeAnnouncements(array ...$files): void
    {
        $sequence = Http::fakeSequence(self::URL);

        foreach ($files as $announcements) {
            $sequence->push(['announcements' => $announcements]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function announcement(string $id, string $date, string $level = 'info', array $extra = []): array
    {
        return ['id' => $id, 'date' => $date, 'level' => $level, 'title' => ['ja' => "{$id} の題名", 'en' => "{$id} title"], ...$extra];
    }

    public function test_current_announcements_are_filtered_sorted_and_localized(): void
    {
        $this->fakeAnnouncements([
            $this->announcement('old', '2026-09-01'),
            $this->announcement('new', '2026-10-05', 'important', ['body' => ['ja' => '本文', 'en' => 'Body'], 'url' => 'https://example.com/new']),
            $this->announcement('expired', '2026-10-06', extra: ['expires' => '2026-10-09']),
            $this->announcement('for-old-versions', '2026-10-07', 'security', ['versions' => '<1.2.0']),
            $this->announcement('for-this-version', '2026-10-08', 'security', ['versions' => '<=1.2.0']),
            // 形の正しくないお知らせは出さない
            $this->announcement('bad url', '2026-10-09'),
            $this->announcement('http-url', '2026-10-09', extra: ['url' => 'http://example.com']),
            $this->announcement('bad-level', '2026-10-09', 'urgent'),
            ['id' => 'no-title', 'date' => '2026-10-09'],
        ]);
        $service = app(AnnouncementService::class);

        $current = $service->current();

        $this->assertSame(['for-this-version', 'new', 'old'], array_column($current, 'id'));
        $this->assertSame('new の題名', $current[1]['title']);
        $this->assertSame('本文', $current[1]['body']);
        $this->assertSame('2026/10/05', $current[1]['date']->format('Y/m/d'));
        $this->assertSame(['for-this-version', 'new'], array_column($service->urgent(), 'id'));

        App::setLocale('en');
        $this->assertSame('new title', $service->current()[1]['title']);
        // 2 回目からはキャッシュを使う
        Http::assertSentCount(1);
    }

    public function test_nothing_is_requested_when_disabled_or_only_the_cache_is_read(): void
    {
        Http::fake();

        config(['biscuit.announcements.enabled' => false]);
        $this->assertSame([], app(AnnouncementService::class)->current());

        config(['biscuit.announcements.enabled' => true]);
        $this->assertSame([], app(AnnouncementService::class)->urgent(fetch: false));

        Http::assertNothingSent();
    }

    public function test_failures_show_nothing_and_are_cached_for_a_while(): void
    {
        Http::fakeSequence(self::URL)->push('error', 500);
        $service = app(AnnouncementService::class);

        $this->assertSame([], $service->current());
        $this->assertSame([], $service->current());
        Http::assertSentCount(1);
    }

    public function test_dashboard_shows_announcements_to_every_admin_and_urgent_ones_on_every_page(): void
    {
        $this->fakeAnnouncements([
            $this->announcement('info-1', '2026-10-01', extra: ['title' => '<script>alert(1)</script>普通のお知らせ']),
            $this->announcement('security-1', '2026-10-02', 'security', ['url' => 'https://example.com/security']),
        ]);

        $this->actingAsAdmin(['role' => AdministratorRole::Admin]);
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-announcements', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;普通のお知らせ', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        // ダッシュボードが作ったキャッシュで、ほかの画面の上部に重要・セキュリティのお知らせだけを出す
        $this->get(route('admin.builder-components.index'))
            ->assertOk()
            ->assertSee('data-dismissible="announcement:security-1"', false)
            ->assertDontSee('data-dismissible="announcement:info-1"', false);
    }

    public function test_command_emails_super_admins_once_per_urgent_announcement(): void
    {
        Notification::fake();
        $superAdmin = Administrator::factory()->create(['role' => AdministratorRole::SuperAdmin]);
        $admin = Administrator::factory()->create(['role' => AdministratorRole::Admin]);
        $first = [$this->announcement('info-1', '2026-10-01'), $this->announcement('important-1', '2026-10-02', 'important')];
        $this->fakeAnnouncements($first, $first, [...$first, $this->announcement('security-1', '2026-10-03', 'security')]);

        $this->artisan('biscuit:check-announcements')->assertSuccessful();
        $this->artisan('biscuit:check-announcements')->assertSuccessful();

        Notification::assertSentToTimes($superAdmin, AnnouncementNotification::class, 1);
        Notification::assertNotSentTo($admin, AnnouncementNotification::class);

        // 新しい重要なお知らせが出たら、それだけを知らせる
        $this->artisan('biscuit:check-announcements')->assertSuccessful();
        Notification::assertSentToTimes($superAdmin, AnnouncementNotification::class, 2);
        Notification::assertSentTo($superAdmin, AnnouncementNotification::class, fn (AnnouncementNotification $notification) => $notification->announcement['id'] === 'security-1');
    }

    public function test_announcement_email_contains_the_title_body_and_link(): void
    {
        $this->fakeAnnouncements([$this->announcement('security-1', '2026-10-02', 'security', ['body' => '更新してください', 'url' => 'https://example.com/security'])]);
        $announcement = app(AnnouncementService::class)->urgent()[0];

        $mail = (new AnnouncementNotification($announcement))->toMail(Administrator::factory()->create());
        $html = (string) $mail->render();

        $this->assertStringContainsString('security-1 の題名', $mail->subject);
        $this->assertStringContainsString('更新してください', $html);
        $this->assertStringContainsString('https://example.com/security', $html);
    }

    public function test_the_check_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('biscuit:check-announcements')->assertSuccessful();
    }
}
