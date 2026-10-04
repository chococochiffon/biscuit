<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\SiteSetting;
use App\Services\UpdateCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * インストール後の診断(biscuit:doctor)と状態(biscuit:status)。
 */
class DoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::fake(['http://front:3000*' => Http::response('ok'), 'api.github.com/*' => Http::response([], 404)]);
        config(['biscuit.update_check.enabled' => false]);
    }

    private function installedSite(): void
    {
        SiteSetting::query()->create(['site_title' => 'ビスケット商店', 'top_use_builder' => false, 'site_icon' => SiteSetting::DEFAULT_SITE_ICON_PATH, 'site_image' => SiteSetting::DEFAULT_SITE_IMAGE_PATH]);
        Administrator::factory()->create();
    }

    public function test_doctor_passes_when_required_checks_pass(): void
    {
        $this->installedSite();

        $this->artisan('biscuit:doctor --offline')
            ->expectsOutputToContain('インストールを終えている')
            ->expectsOutputToContain('最新の版を使っている')
            ->assertSuccessful();
    }

    public function test_doctor_fails_without_an_administrator(): void
    {
        SiteSetting::query()->create(['site_title' => 'x', 'site_icon' => SiteSetting::DEFAULT_SITE_ICON_PATH, 'site_image' => SiteSetting::DEFAULT_SITE_IMAGE_PATH]);

        $this->artisan('biscuit:doctor --offline')->assertFailed();
    }

    public function test_doctor_can_ignore_a_check(): void
    {
        SiteSetting::query()->create(['site_title' => 'x', 'site_icon' => SiteSetting::DEFAULT_SITE_ICON_PATH, 'site_image' => SiteSetting::DEFAULT_SITE_IMAGE_PATH]);

        $this->artisan('biscuit:doctor --offline --ignore=administrator')->assertSuccessful();
    }

    public function test_doctor_outputs_json_with_a_newer_release(): void
    {
        $this->installedSite();
        config(['biscuit.update_check.enabled' => true]);
        Cache::put(UpdateCheckService::CACHE_KEY, ['release' => ['version' => '9.0.0', 'name' => 'v9.0.0', 'url' => 'https://example.com', 'published_at' => null, 'notes' => '']]);

        $this->assertSame(0, Artisan::call('biscuit:doctor', ['--offline' => true, '--json' => true]));
        $result = json_decode(trim(Artisan::output()), true);

        $this->assertTrue($result['passes']);
        $update = collect($result['checks'])->firstWhere('key', 'update');
        $this->assertFalse($update['ok']);
        $this->assertStringContainsString('v9.0.0', $update['detail']);
    }

    public function test_design_check_accepts_a_top_page_without_the_builder(): void
    {
        $this->installedSite();

        Artisan::call('biscuit:doctor', ['--offline' => true, '--json' => true]);
        $checks = collect(json_decode(trim(Artisan::output()), true)['checks'])->keyBy('key');

        $this->assertTrue($checks['design']['ok']);
        // テストのメールの設定(array)は SMTP ではない
        $this->assertFalse($checks['mail']['ok']);
    }

    public function test_status_shows_the_version_and_the_database(): void
    {
        $this->installedSite();

        $this->artisan('biscuit:status')
            ->expectsOutputToContain('v'.config('biscuit.version'))
            ->expectsOutputToContain('接続できる')
            ->assertSuccessful();

        $this->assertSame(0, Artisan::call('biscuit:status', ['--json' => true]));
        $status = json_decode(trim(Artisan::output()), true);
        $this->assertSame(config('biscuit.version'), $status['version']);
        $this->assertTrue($status['installed']);
        $this->assertSame(0, $status['database']['pending_migrations']);
        $this->assertSame(1, $status['database']['administrators']);
    }
}
