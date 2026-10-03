<?php

namespace Tests\Feature;

use App\Installer\ApplicationInstaller;
use App\Installer\EnvironmentWriter;
use App\Installer\HostBridge;
use App\Installer\InstallationState;
use App\Installer\InstallerStep;
use App\Installer\InstallerStepException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class InstallerApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['installer.assume_installed' => false]);
        $state = app(InstallationState::class);
        $state->markCompleted(InstallerStep::Requirements);
        $state->markCompleted(InstallerStep::Database);
    }

    private function writeStatus(string $status, ?string $stage = null, ?string $message = null): void
    {
        Storage::disk('local')->put(HostBridge::STATUS_FILE, json_encode(['action' => 'start-services', 'status' => $status, 'stage' => $stage, 'message' => $message]));
    }

    public function test_starting_the_setup_places_only_a_known_request_for_install_sh(): void
    {
        $this->get(route('installer.application'))->assertOk()->assertSee('data-application-start', false);

        $this->post(route('installer.application.start'))->assertRedirect(route('installer.application'));

        $this->assertSame('start-services', json_decode(Storage::disk('local')->get(HostBridge::REQUEST_FILE), true)['action']);
        $this->get(route('installer.application'))->assertOk()->assertSee('data-application-running', false)->assertSee('http-equiv="refresh"', false);

        $this->expectException(InvalidArgumentException::class);
        app(HostBridge::class)->request('docker compose down');
    }

    public function test_progress_failure_and_retry_are_shown(): void
    {
        $this->writeStatus('running', 'database', 'データベースの起動を待っています');
        // 処理の一覧: 終えた処理・今の処理・まだの処理
        $this->get(route('installer.application'))
            ->assertSee('data-application-running', false)
            ->assertSee('データベースの起動を待っています')
            ->assertSee('data-stage="start" data-state="done"', false)
            ->assertSee('data-stage="database" data-state="running"', false)
            ->assertSee('data-stage="front" data-state="waiting"', false)
            ->assertSee('aria-valuenow="2"', false);
        // 動いている間は合図を置き直さない
        $this->post(route('installer.application.start'));
        Storage::disk('local')->assertMissing(HostBridge::REQUEST_FILE);

        $this->writeStatus('failed', 'migration', 'データベースのマイグレーションに失敗しました。');
        $this->get(route('installer.application'))
            ->assertSee('data-application-failed', false)
            ->assertSee('データベースのマイグレーションに失敗しました。')
            ->assertSee('再試行')
            // マイグレーションの失敗は「テーブルと初期データを用意する」の失敗として出す
            ->assertSee('data-stage="application" data-state="failed"', false);

        $this->post(route('installer.application.start'));
        Storage::disk('local')->assertExists(HostBridge::REQUEST_FILE);
        Storage::disk('local')->assertMissing(HostBridge::STATUS_FILE);
    }

    public function test_finished_application_step_moves_to_the_next_step(): void
    {
        app(InstallationState::class)->markCompleted(InstallerStep::Application);

        $this->get(route('installer.application'))->assertRedirect(route('installer.site'));
    }

    public function test_application_installer_migrates_and_completes_the_step(): void
    {
        app(ApplicationInstaller::class)->run(connectAttempts: 1);

        $this->assertTrue(app(InstallationState::class)->isCompleted(InstallerStep::Application));
        $this->assertTrue(Schema::hasTable('administrators'));
    }

    public function test_application_installer_reports_database_connection_failures(): void
    {
        config(['database.connections.broken' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => 'x'], 'database.default' => 'broken']);

        try {
            app(ApplicationInstaller::class)->run(connectAttempts: 1);
            $this->fail('接続できないのに進んだ');
        } catch (InstallerStepException $exception) {
            $this->assertSame('database', $exception->stage);
        } finally {
            config(['database.default' => 'sqlite']);
        }

        $this->assertFalse(app(InstallationState::class)->isCompleted(InstallerStep::Application));
    }

    public function test_step_command_runs_only_allowed_steps_in_order(): void
    {
        $this->artisan('biscuit:install --step=administrator')->assertExitCode(2);

        app(InstallationState::class)->markIncomplete(InstallerStep::Database);
        $this->artisan('biscuit:install --step=application')->assertFailed();
    }

    public function test_prepare_writes_production_defaults_and_keeps_existing_keys(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($path, "APP_ENV=local\nAPP_DEBUG=true\nDB_PASSWORD=password\nPAGE_VIEW_FORWARD_KEY=\n");
        $this->app->instance(EnvironmentWriter::class, new EnvironmentWriter($path));
        app(InstallationState::class)->markIncomplete(InstallerStep::Database);

        try {
            $this->artisan('biscuit:install --prepare')->assertSuccessful();
            $env = (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }

        $this->assertStringContainsString("APP_ENV='production'", $env);
        $this->assertStringContainsString("APP_DEBUG='false'", $env);
        // 配布物の DB のパスワードは消す
        $this->assertStringContainsString("DB_PASSWORD=''", $env);
        $this->assertMatchesRegularExpression("/^PAGE_VIEW_FORWARD_KEY='[A-Za-z0-9]{48}'$/m", $env);
        $this->assertStringContainsString("APP_URL='http://localhost:8080'", $env);
        $this->assertStringContainsString("FRONT_URL='http://localhost'", $env);
        $this->assertStringContainsString("BISCUIT_ADMIN_PORT='8080'", $env);
    }
}
