<?php

namespace Tests\Feature;

use App\Installer\InstallationState;
use App\Installer\InstallerLog;
use App\Installer\InstallerManager;
use App\Installer\InstallerStep;
use App\Models\Administrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        // ほかのテストはインストール済みとして動かす(phpunit.xml)。ここではインストール前の動きを確かめる
        config(['installer.assume_installed' => false]);
    }

    public function test_admin_pages_and_api_lead_to_the_installer_before_installation(): void
    {
        $this->get(route('admin.login'))->assertRedirect(route('installer.requirements'));
        $this->getJson('/api/site-setting')->assertStatus(503);
        // ヘルスチェックはそのまま
        $this->get('/up')->assertOk();
    }

    public function test_the_installer_shows_the_requirements_and_starts(): void
    {
        $this->get(route('installer.requirements'))
            ->assertOk()
            ->assertSee('data-requirements', false)
            ->assertSee('aria-current="step"', false);

        $this->post(route('installer.start'))->assertRedirect(route('installer.database'));
        $this->assertTrue(app(InstallationState::class)->isCompleted(InstallerStep::Requirements));
    }

    public function test_steps_cannot_be_entered_before_the_previous_steps(): void
    {
        $this->get(route('installer.site'))->assertRedirect(route('installer.requirements'));

        app(InstallationState::class)->markCompleted(InstallerStep::Requirements);
        $this->get(route('installer.site'))->assertRedirect(route('installer.database'));
        $this->get(route('installer.database'))->assertOk();
    }

    public function test_the_installer_cannot_be_used_once_installed(): void
    {
        app(InstallerManager::class)->lock();

        $this->get(route('installer.requirements'))->assertRedirect(route('admin.login'));
        $this->post(route('installer.start'))->assertRedirect(route('admin.login'));
        $this->getJson(route('installer.database'))->assertNotFound();
        $this->get(route('admin.login'))->assertOk();
    }

    public function test_an_existing_installation_with_administrators_is_detected_and_locked(): void
    {
        Administrator::factory()->create();

        $this->get(route('installer.requirements'))->assertRedirect(route('admin.login'));
        Storage::disk('local')->assertExists(InstallerManager::LOCK_FILE);
    }

    public function test_lock_clears_the_installation_state(): void
    {
        $state = app(InstallationState::class);
        $state->markCompleted(InstallerStep::Requirements);
        $state->putSecret('db_password', 'S3cret-Passw0rd!');

        app(InstallerManager::class)->lock();

        $this->assertFalse($state->isCompleted(InstallerStep::Requirements));
        $this->assertNull($state->secret('db_password'));
        $this->assertSame([InstallerManager::LOCK_FILE], Storage::disk('local')->allFiles());
    }

    public function test_state_resumes_and_secrets_are_not_stored_in_plain_text(): void
    {
        $state = app(InstallationState::class);
        $state->markCompleted(InstallerStep::Requirements);
        $state->put('db_database', 'biscuit_site');
        $state->putSecret('db_password', 'S3cret-Passw0rd!');

        // 別のリクエスト(新しいインスタンス)でも続きから読める
        $resumed = new InstallationState;
        $this->assertTrue($resumed->isCompleted(InstallerStep::Requirements));
        $this->assertSame('biscuit_site', $resumed->get('db_database'));
        $this->assertSame('S3cret-Passw0rd!', $resumed->secret('db_password'));
        $this->assertSame(InstallerStep::Database, app(InstallerManager::class)->currentStep());

        foreach (Storage::disk('local')->allFiles() as $file) {
            $this->assertStringNotContainsString('S3cret-Passw0rd!', (string) Storage::disk('local')->get($file), $file);
        }
    }

    public function test_installer_log_redacts_secrets(): void
    {
        $this->assertSame(
            ['database' => 'biscuit_site', 'db_password' => '[REDACTED]', 'nested' => ['APP_KEY' => '[REDACTED]', 'admin_password' => '[REDACTED]']],
            InstallerLog::redact(['database' => 'biscuit_site', 'db_password' => 'x', 'nested' => ['APP_KEY' => 'base64:x', 'admin_password' => 'y']]),
        );
    }

    public function test_status_command_shows_the_steps(): void
    {
        $this->artisan('biscuit:install --status')->expectsOutputToContain('環境の確認')->assertSuccessful();

        app(InstallerManager::class)->lock();
        $this->artisan('biscuit:install --status')->expectsOutputToContain('インストール済み')->assertSuccessful();
    }
}
