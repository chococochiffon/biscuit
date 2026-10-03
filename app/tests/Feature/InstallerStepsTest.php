<?php

namespace Tests\Feature;

use App\Enums\AdministratorRole;
use App\Installer\EnvironmentWriter;
use App\Installer\InstallationState;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Installer\TemplateInstaller;
use App\Models\Administrator;
use App\Models\GalleryImage;
use App\Models\LayoutBlock;
use App\Models\PageBuilder;
use App\Models\PageBuilderTemplate;
use App\Models\SiteSetting;
use App\Support\Builder\BuilderContent;
use Database\Seeders\InstallSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

        $this->envPath = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($this->envPath, "APP_URL=http://localhost:8080\nFRONT_URL=http://localhost\nMAIL_MAILER=smtp\nMAIL_HOST=mailpit\n");
        $this->app->instance(EnvironmentWriter::class, new EnvironmentWriter($this->envPath));
    }

    protected function tearDown(): void
    {
        @unlink($this->envPath);

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
        return ['host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'starttls', 'username' => 'mailer', 'password' => 'p$ss"word', 'from_address' => 'no-reply@example.com', 'test_to' => 'owner@example.com', ...$overrides];
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

    public function test_design_step_creates_the_default_template_as_builder_content(): void
    {
        $this->seed(InstallSeeder::class);
        SiteSetting::query()->create(['site_title' => 'ビスケット商店', 'description' => '<b>手作り</b>のお菓子', 'site_icon' => SiteSetting::DEFAULT_SITE_ICON_PATH, 'site_image' => SiteSetting::DEFAULT_SITE_IMAGE_PATH]);
        $this->completeUntil(InstallerStep::Design);

        $this->post(route('installer.design.store'))->assertRedirect(route('installer.finalize'));

        $builder = PageBuilder::top();
        $this->assertTrue($builder->isPublished());
        $this->assertSame(1, $builder->versions()->count());
        $this->assertTrue(SiteSetting::current()->top_use_builder);
        $this->assertTrue(LayoutBlock::query()->exists());

        $nodes = iterator_to_array(BuilderContent::nodes($builder->published_content), false);
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

        $this->get(route('installer.finalize'))->assertOk()->assertSee('data-finalize-checks', false)->assertDontSee('bi-x-circle-fill', false);

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
}
