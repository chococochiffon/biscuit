<?php

namespace Tests\Feature;

use App\Enums\AdministratorRole;
use App\Installer\EnvironmentWriter;
use App\Installer\HealthChecker;
use App\Installer\InstallationState;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Installer\SiteInstaller;
use App\Installer\TemplateInstaller;
use App\Models\Administrator;
use App\Models\GalleryImage;
use App\Models\LayoutBlock;
use App\Models\PageBuilder;
use App\Models\PageBuilderTemplate;
use App\Models\SiteSetting;
use App\Providers\AppServiceProvider;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Database\Seeders\InstallSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class InstallerStepsTest extends TestCase
{
    use RefreshDatabase;

    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        config(['installer.assume_installed' => false]);
        Http::fake(['http://front:3000*' => Http::response('ok')]);

        $this->envPath = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($this->envPath, "APP_URL=http://localhost:8080\nFRONT_URL=http://localhost\nMAIL_MAILER=smtp\nMAIL_HOST=mailpit\n");
        $this->app->instance(EnvironmentWriter::class, new EnvironmentWriter($this->envPath));
    }

    protected function tearDown(): void
    {
        @unlink($this->envPath);
        TrustProxies::flushState();

        parent::tearDown();
    }

    /**
     * 渡した段より前の段を済みにする。
     */
    private function completeUntil(InstallerStep $step): void
    {
        foreach ($step->previous() as $previous) {
            app(InstallationState::class)->markCompleted($previous);
        }
    }

    private function env(): string
    {
        return (string) file_get_contents($this->envPath);
    }

    /**
     * インストールを終えていないとき(TRUSTED_PROXIES がまだない)の設定で、サービスプロバイダーを起動し直す。
     */
    private function bootBeforeInstallation(): void
    {
        config(['biscuit.trusted_proxies' => []]);
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_installer_uses_https_urls_behind_an_https_reverse_proxy(): void
    {
        $this->bootBeforeInstallation();

        // HTTPS のリバースプロキシ(プライベートなネットワーク)の裏では、CSS とフォームの送信先も https:// にする。ホスト名は信じない
        $response = $this->get(route('installer.requirements'), ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example.com'])->assertOk();
        $response->assertSee('action="https://localhost/install"', false)->assertDontSee('evil.example.com')->assertDontSee('http://localhost/', false);
    }

    public function test_installer_uses_http_urls_without_a_reverse_proxy(): void
    {
        $this->bootBeforeInstallation();

        $this->get(route('installer.requirements'))->assertOk()->assertSee('action="http://localhost/install"', false);
    }

    public function test_installer_ignores_forwarded_headers_from_outside_the_private_networks(): void
    {
        $this->bootBeforeInstallation();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get(route('installer.requirements'), ['X-Forwarded-Proto' => 'https'])
            ->assertOk()
            ->assertSee('action="http://localhost/install"', false);
    }

    public function test_installed_sites_do_not_trust_forwarded_headers_without_trusted_proxies(): void
    {
        Storage::disk('local')->put(InstallerManager::LOCK_FILE, '{}');
        $this->bootBeforeInstallation();

        $this->get(route('admin.login'), ['X-Forwarded-Proto' => 'https'])->assertOk()->assertSee('action="http://localhost/admin/login"', false);
    }

    public function test_site_step_suggests_the_current_url_as_the_admin_url(): void
    {
        $this->bootBeforeInstallation();
        $this->completeUntil(InstallerStep::Site);

        $this->get(route('installer.site'), ['X-Forwarded-Proto' => 'https'])->assertOk()->assertSee('value="https://localhost"', false);
    }

    public function test_site_step_saves_the_site_setting_and_urls(): void
    {
        $this->completeUntil(InstallerStep::Site);

        $this->post(route('installer.site.store'), [
            'site_title' => 'ビスケット商店',
            'description' => '手作りのお菓子のお店です。',
            'locale' => 'ja',
            'timezone' => 'Asia/Tokyo',
            'front_url' => 'https://example.com/',
            'admin_url' => 'https://admin.example.com',
        ])->assertRedirect(route('installer.mail'));

        $setting = SiteSetting::current();
        $this->assertSame('ビスケット商店', $setting->site_title);
        $this->assertSame('https://example.com', $setting->front_url);
        $this->assertSame('https://admin.example.com/api', $setting->api_url);
        $this->assertStringContainsString("APP_URL='https://admin.example.com'", $this->env());
        $this->assertStringContainsString("FRONT_URL='https://example.com'", $this->env());
        $this->assertStringContainsString("APP_TIMEZONE='Asia/Tokyo'", $this->env());
        // https で公開するときは、外側のリバースプロキシを信じる
        $this->assertStringContainsString("TRUSTED_PROXIES='".SiteInstaller::PRIVATE_NETWORKS."'", $this->env());
    }

    public function test_site_step_does_not_trust_proxies_for_http_urls(): void
    {
        $this->completeUntil(InstallerStep::Site);

        $this->post(route('installer.site.store'), [
            'site_title' => 'x', 'locale' => 'ja', 'timezone' => 'Asia/Tokyo', 'front_url' => 'http://localhost', 'admin_url' => 'http://localhost:8080',
        ])->assertRedirect(route('installer.mail'));

        $this->assertStringContainsString("TRUSTED_PROXIES=''", $this->env());
    }

    public function test_site_step_rejects_the_same_url_for_both_sites(): void
    {
        $this->completeUntil(InstallerStep::Site);

        $this->from(route('installer.site'))->post(route('installer.site.store'), [
            'site_title' => 'x', 'locale' => 'ja', 'timezone' => 'Asia/Tokyo', 'front_url' => 'https://example.com', 'admin_url' => 'https://example.com',
        ])->assertSessionHasErrors('admin_url');
    }

    /**
     * @return array<string, string|int>
     */
    private function mailInput(array $overrides = []): array
    {
        return ['driver' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'starttls', 'username' => 'mailer', 'password' => 'p$ss"word', 'from_address' => 'no-reply@example.com', 'test_to' => 'owner@example.com', ...$overrides];
    }

    public function test_mail_step_sends_a_test_email_and_writes_the_settings(): void
    {
        Mail::fake();
        $this->completeUntil(InstallerStep::Mail);

        $this->post(route('installer.mail.store'), $this->mailInput())->assertRedirect(route('installer.administrator'));

        $this->assertStringContainsString("MAIL_HOST='smtp.example.com'", $this->env());
        $this->assertStringContainsString("MAIL_PASSWORD='p\$ss\"word'", $this->env());
        $this->assertStringContainsString("MAIL_SCHEME='smtp'", $this->env());
        $this->assertTrue(app(InstallationState::class)->isCompleted(InstallerStep::Mail));
    }

    public function test_mail_step_cannot_proceed_when_the_test_email_fails(): void
    {
        $this->completeUntil(InstallerStep::Mail);
        $before = $this->env();

        $response = $this->post(route('installer.mail.store'), $this->mailInput(['host' => '127.0.0.1', 'port' => 1, 'password' => 'Secret-Smtp-Pass']))
            ->assertRedirect(route('installer.mail'));

        $this->assertStringContainsString('試しのメールを送れませんでした。', (string) session('error'));
        $this->assertStringNotContainsString('Secret-Smtp-Pass', (string) session('error'));
        $this->assertNull(session()->getOldInput('password'));
        $this->assertSame($before, $this->env());
        $this->assertFalse(app(InstallationState::class)->isCompleted(InstallerStep::Mail));
    }

    public function test_mail_step_can_send_with_resend(): void
    {
        Mail::fake();
        $this->completeUntil(InstallerStep::Mail);

        // SMTP の欄は空でも、Resend を選べば受け付ける
        $this->post(route('installer.mail.store'), ['driver' => 'resend', 'host' => '', 'port' => '', 'api_key' => 're_test_123', 'from_address' => 'no-reply@example.com', 'test_to' => 'owner@example.com'])
            ->assertRedirect(route('installer.administrator'));

        $this->assertStringContainsString("MAIL_MAILER='resend'", $this->env());
        $this->assertStringContainsString("RESEND_API_KEY='re_test_123'", $this->env());
        $this->assertStringContainsString("MAIL_FROM_ADDRESS='no-reply@example.com'", $this->env());
        $this->assertStringContainsString('MAIL_HOST=mailpit', $this->env());
        $this->assertNull(config('mail.mailers.installer_test'));
        $this->assertTrue(app(InstallationState::class)->isCompleted(InstallerStep::Mail));
    }

    public function test_mail_step_requires_a_resend_api_key(): void
    {
        $this->completeUntil(InstallerStep::Mail);
        $input = ['driver' => 'resend', 'from_address' => 'no-reply@example.com', 'test_to' => 'owner@example.com'];

        $this->post(route('installer.mail.store'), $input)->assertSessionHasErrors('api_key');
        $this->post(route('installer.mail.store'), [...$input, 'api_key' => 'not-a-key'])->assertSessionHasErrors('api_key');
        $this->post(route('installer.mail.store'), [...$input, 'driver' => 'sendmail', 'api_key' => 're_test_123'])->assertSessionHasErrors('driver');
        $this->assertFalse(app(InstallationState::class)->isCompleted(InstallerStep::Mail));
    }

    public function test_mail_step_hides_the_resend_api_key_when_the_test_email_fails(): void
    {
        $this->completeUntil(InstallerStep::Mail);
        $before = $this->env();
        Mail::shouldReceive('mailer->raw')->andThrow(new RuntimeException('API key re_secret_456 is invalid'));
        Mail::shouldReceive('purge')->with('installer_test');

        $this->post(route('installer.mail.store'), ['driver' => 'resend', 'api_key' => 're_secret_456', 'from_address' => 'no-reply@example.com', 'test_to' => 'owner@example.com'])
            ->assertRedirect(route('installer.mail'));

        $this->assertStringContainsString('Resend の API キー', (string) session('error'));
        $this->assertStringNotContainsString('re_secret_456', (string) session('error'));
        $this->assertNull(session()->getOldInput('api_key'));
        $this->assertSame($before, $this->env());
    }

    public function test_administrator_step_creates_one_super_administrator(): void
    {
        $this->completeUntil(InstallerStep::Administrator);
        $messages = [];
        Log::listen(function ($event) use (&$messages) {
            $messages[] = $event->message.' '.json_encode($event->context);
        });

        $this->from(route('installer.administrator'))->post(route('installer.administrator.store'), [
            'name' => 'オーナー', 'email' => 'owner@example.com', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->post(route('installer.administrator.store'), [
            'name' => 'オーナー', 'email' => 'owner@example.com', 'password' => 'Kq7-zR2_mW9!xP4', 'password_confirmation' => 'Kq7-zR2_mW9!xP4',
        ])->assertRedirect(route('installer.design'));

        $administrator = Administrator::query()->sole();
        $this->assertSame(AdministratorRole::SuperAdmin, $administrator->role);
        $this->assertTrue(password_verify('Kq7-zR2_mW9!xP4', $administrator->password));
        foreach ($messages as $message) {
            $this->assertStringNotContainsString('Kq7-zR2_mW9!xP4', $message);
        }

        // 管理者がいても、インストールの途中はインストール済みにならない(続きの段に入れる)
        $this->assertFalse(app(InstallerManager::class)->isInstalled());
        $this->get(route('installer.design'))->assertOk();

        // 作り終えたあとに戻っても、2 人目は作らない
        $this->get(route('installer.administrator'))->assertRedirect(route('installer.design'));
        $this->post(route('installer.administrator.store'), [
            'name' => '別の人', 'email' => 'other@example.com', 'password' => 'Zz8-yX3_wV7!uT5', 'password_confirmation' => 'Zz8-yX3_wV7!uT5',
        ]);
        $this->assertSame(1, Administrator::query()->count());
    }

    public function test_install_seeder_adds_master_data_without_a_fixed_administrator(): void
    {
        $this->seed(InstallSeeder::class);

        $this->assertSame(0, Administrator::query()->count());
        $this->assertTrue(PageBuilderTemplate::query()->exists());
        $this->assertSame(4, GalleryImage::query()->count());
        Storage::disk('public')->assertExists(GalleryImage::query()->pluck('image')->all());
        Storage::disk('public')->assertExists(SiteSetting::DEFAULT_SITE_ICON_PATH);
    }

    public function test_design_step_default_does_not_use_the_builder_on_the_top_page(): void
    {
        $this->seed(InstallSeeder::class);
        SiteSetting::query()->create(['site_title' => 'ビスケット商店', 'description' => '<b>手作り</b>のお菓子', 'site_icon' => SiteSetting::DEFAULT_SITE_ICON_PATH, 'site_image' => SiteSetting::DEFAULT_SITE_IMAGE_PATH]);
        $this->completeUntil(InstallerStep::Design);

        $this->post(route('installer.design.store'), ['design' => 'default'])->assertRedirect(route('installer.finalize'));

        // トップにビルダーは使わず、デフォルトのひな形は公開しないで下書きに入れておく
        $builder = PageBuilder::top();
        $this->assertFalse($builder->isPublished());
        $this->assertSame(0, $builder->versions()->count());
        $this->assertSame(SchemaMigrator::CURRENT_VERSION, $builder->draft_content['version']);
        $this->assertFalse(SiteSetting::current()->top_use_builder);
        $this->assertTrue(LayoutBlock::query()->exists());

        $nodes = iterator_to_array(BuilderContent::nodes($builder->draft_content), false);
        $texts = array_column(array_column($nodes, 'props'), 'text');
        $this->assertContains('ビスケット商店', $texts);
        $this->assertContains('新着記事', $texts);
        $this->assertContains('article-list', array_column($nodes, 'type'));
        // 説明は HTML を効かせない
        $html = array_values(array_filter(array_column(array_column($nodes, 'props'), 'html')))[0];
        $this->assertStringContainsString('&lt;b&gt;手作り&lt;/b&gt;', $html);
    }

    public function test_default_template_skips_the_description_when_empty(): void
    {
        $content = app(TemplateInstaller::class)->defaultContent('ビスケット商店', '');

        $this->assertNotContains('text', array_column(iterator_to_array(BuilderContent::nodes($content), false), 'type'));
    }

    public function test_finalize_completes_the_installation_and_locks_the_installer(): void
    {
        $this->seed(InstallSeeder::class);
        SiteSetting::query()->create(['site_title' => 'ビスケット商店', 'front_url' => 'https://example.com', 'site_icon' => SiteSetting::DEFAULT_SITE_ICON_PATH, 'site_image' => SiteSetting::DEFAULT_SITE_IMAGE_PATH]);
        Administrator::factory()->create(['role' => AdministratorRole::SuperAdmin]);
        $this->completeUntil(InstallerStep::Design);
        app(TemplateInstaller::class)->installDefault();

        $this->get(route('installer.finalize'))
            ->assertOk()
            ->assertSee('data-finalize-checks="required"', false)
            ->assertDontSee('bi-x-circle-fill', false)
            // 推奨の項目は満たしていなくても完了できる(テストの環境は本番の設定ではない)
            ->assertSee('data-check="environment" data-ok="false"', false)
            ->assertSee('data-check="front" data-ok="true"', false);

        $this->post(route('installer.finalize.store'))
            ->assertOk()
            ->assertSee('data-install-complete', false)
            ->assertSee('https://example.com', false);

        Storage::disk('local')->assertExists(InstallerManager::LOCK_FILE);
        Storage::disk('local')->assertMissing(InstallationState::DIRECTORY.'/state.json');
        $this->get(route('installer.requirements'))->assertRedirect(route('admin.login'));
        $this->post(route('installer.administrator.store'), [])->assertRedirect(route('admin.login'));
        $this->get(route('admin.login'))->assertOk();
    }

    public function test_finalize_cannot_complete_while_items_are_missing(): void
    {
        $this->completeUntil(InstallerStep::Finalize);

        $this->post(route('installer.finalize.store'))->assertRedirect(route('installer.finalize'));
        Storage::disk('local')->assertMissing(InstallerManager::LOCK_FILE);
    }

    public function test_administrator_step_can_generate_a_password(): void
    {
        $this->completeUntil(InstallerStep::Administrator);

        $response = $this->post(route('installer.administrator.generate'), ['name' => 'オーナー', 'email' => 'owner@example.com'])
            ->assertOk()
            ->assertSee('data-generated-password', false)
            ->assertSee('value="owner@example.com"', false);

        preg_match('/<code class="user-select-all">([^<]+)<\/code>/', $response->getContent(), $matches);
        $password = html_entity_decode($matches[1]);
        $this->assertSame(config('installer.generated_password_length'), strlen($password));
        $this->assertNull(session('generatedPassword'));

        $this->post(route('installer.administrator.store'), ['name' => 'オーナー', 'email' => 'owner@example.com', 'password' => $password, 'password_confirmation' => $password])
            ->assertRedirect(route('installer.design'));
    }

    public function test_the_installer_can_be_resumed_and_completed_steps_can_be_edited(): void
    {
        $this->completeUntil(InstallerStep::Mail);

        // 最初の画面に、続きの段へのボタンを出す
        $this->get(route('installer.requirements'))->assertOk()->assertSee('data-installer-resume', false)->assertSee(route('installer.mail'), false);

        // 終えたサイトの段へは戻って直せ、ステッパーにリンクを出す。アプリケーションの段は戻れない
        $this->get(route('installer.mail'))->assertOk()->assertSee('href="'.route('installer.site').'"', false)->assertDontSee('href="'.route('installer.application').'"', false);
        $this->get(route('installer.site'))->assertOk();
        $this->get(route('installer.application'))->assertRedirect(route('installer.mail'));

        // DB を作ったあとは、データベースの段には戻れない
        $this->get(route('installer.database'))->assertRedirect(route('installer.mail'));
        $this->post(route('installer.database.store'), [])->assertRedirect(route('installer.mail'));
    }

    public function test_database_step_can_be_edited_until_the_application_step_is_done(): void
    {
        $this->completeUntil(InstallerStep::Application);
        app(InstallationState::class)->markCompleted(InstallerStep::Database);

        $this->get(route('installer.database'))->assertOk();
    }

    public function test_the_first_page_does_not_offer_to_resume_a_new_installation(): void
    {
        $this->get(route('installer.requirements'))->assertOk()->assertDontSee('data-installer-resume', false);
    }

    public function test_mail_step_can_be_edited_and_keeps_the_current_password(): void
    {
        Mail::fake();
        $this->completeUntil(InstallerStep::Mail);
        $this->post(route('installer.mail.store'), $this->mailInput(['encryption' => 'ssl', 'port' => 465]))->assertRedirect(route('installer.administrator'));

        // 戻って開くと、今の設定が入っていて、パスワードは出さない
        $this->get(route('installer.mail'))
            ->assertOk()
            ->assertSee('value="smtp.example.com"', false)
            ->assertSee('<option value="ssl" selected', false)
            ->assertDontSee('p$ss&quot;word', false)
            ->assertSee('空のままにすると、今のパスワードを使います。');

        $this->post(route('installer.mail.store'), $this->mailInput(['host' => 'smtp2.example.com', 'password' => '']))->assertRedirect(route('installer.administrator'));
        $this->assertStringContainsString("MAIL_HOST='smtp2.example.com'", $this->env());
        $this->assertStringContainsString("MAIL_PASSWORD='p\$ss\"word'", $this->env());
    }

    public function test_mail_step_can_be_edited_and_keeps_the_current_resend_api_key(): void
    {
        Mail::fake();
        $this->completeUntil(InstallerStep::Mail);
        $input = ['driver' => 'resend', 'api_key' => 're_test_123', 'from_address' => 'no-reply@example.com', 'test_to' => 'owner@example.com'];
        $this->post(route('installer.mail.store'), $input)->assertRedirect(route('installer.administrator'));

        // 戻って開くと Resend が選ばれていて、API キーは出さない
        $this->get(route('installer.mail'))
            ->assertOk()
            ->assertSee('value="resend" checked', false)
            ->assertDontSee('re_test_123')
            ->assertSee('空のままにすると、今の API キーを使います。');

        $this->post(route('installer.mail.store'), [...$input, 'api_key' => '', 'from_address' => 'info@example.com'])->assertRedirect(route('installer.administrator'));
        $this->assertStringContainsString("RESEND_API_KEY='re_test_123'", $this->env());
        $this->assertStringContainsString("MAIL_FROM_ADDRESS='info@example.com'", $this->env());

        // SMTP に切り替えるときは、SMTP の設定を入れ直す
        $this->post(route('installer.mail.store'), $this->mailInput())->assertRedirect(route('installer.administrator'));
        $this->assertStringContainsString("MAIL_MAILER='smtp'", $this->env());
    }

    public function test_health_checker_separates_required_and_recommended_checks(): void
    {
        $checks = collect(app(HealthChecker::class)->check())->keyBy('key');

        // 管理者・サイト・デザインがまだないため、必須を満たさない
        $this->assertFalse($checks['administrator']['ok']);
        $this->assertFalse(HealthChecker::passes($checks->values()->all()));
        $this->assertTrue($checks['database']['ok']);
        $this->assertTrue($checks['migrations']['ok']);
        $this->assertSame('recommended', $checks['scheduler']['level']);

        // 推奨の項目が満たせなくても、必須を満たせば完了できる
        $this->assertTrue(HealthChecker::passes([
            ['level' => 'required', 'ok' => true],
            ['level' => 'recommended', 'ok' => false],
        ]));
    }

    public function test_health_checker_sees_the_scheduler_and_the_front(): void
    {
        $this->assertFalse(collect(app(HealthChecker::class)->check())->firstWhere('key', 'scheduler')['ok']);

        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertTrue(collect(app(HealthChecker::class)->check())->firstWhere('key', 'scheduler')['ok']);

        $this->travel(config('installer.scheduler_heartbeat_minutes') + 1)->minutes();
        $this->assertFalse(collect(app(HealthChecker::class)->check())->firstWhere('key', 'scheduler')['ok']);

        // 同じ URL への Http::fake は最初の応答が残るため、別の URL で確かめる(パターンの先頭には * が付くため、setUp の URL は http:// から書く)
        config(['installer.front_internal_url' => 'http://broken-front:3000']);
        Http::fake(['broken-front:3000*' => Http::response('error', 502)]);
        $this->assertFalse(collect(app(HealthChecker::class)->check())->firstWhere('key', 'front')['ok']);

        // インストール中の公開側の「準備中」(503)は届いたとみなす
        config(['installer.front_internal_url' => 'http://preparing-front:3000']);
        Http::fake(['preparing-front:3000*' => Http::response('preparing', 503)]);
        $this->assertTrue(collect(app(HealthChecker::class)->check())->firstWhere('key', 'front')['ok']);
    }

    public function test_status_command_shows_the_health_checks(): void
    {
        $this->artisan('biscuit:install', ['--status' => true])
            ->expectsOutputToContain('インストールの確認')
            ->expectsOutputToContain('スケジューラーが動いている');
    }
}
