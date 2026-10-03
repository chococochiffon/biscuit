<?php

namespace Tests\Feature;

use App\Installer\EnvironmentWriter;
use App\Installer\InstallationState;
use App\Installer\InstallerStep;
use App\Installer\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstallerDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['installer.assume_installed' => false]);
        app(InstallationState::class)->markCompleted(InstallerStep::Requirements);

        // 本物の .env は書き換えず、テスト用の .env に書く
        $this->envPath = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($this->envPath, "APP_NAME=Biscuit\nDB_CONNECTION=mysql\nDB_HOST=db\nDB_PASSWORD=password\nMAIL_HOST=mailpit\n");
        $this->app->instance(EnvironmentWriter::class, new EnvironmentWriter($this->envPath));
    }

    protected function tearDown(): void
    {
        @unlink($this->envPath);

        parent::tearDown();
    }

    /**
     * @return array<string, string>
     */
    private function input(array $overrides = []): array
    {
        return ['database' => 'biscuit_site', 'username' => 'biscuit_user', 'password' => 'Kq7-zR2_mW9!xP4', 'password_confirmation' => 'Kq7-zR2_mW9!xP4', ...$overrides];
    }

    public function test_a_safe_password_writes_the_database_settings_to_env(): void
    {
        $this->post(route('installer.database.store'), $this->input())->assertRedirect(route('installer.application'));

        $env = (string) file_get_contents($this->envPath);
        $this->assertStringContainsString("DB_DATABASE='biscuit_site'", $env);
        $this->assertStringContainsString("DB_USERNAME='biscuit_user'", $env);
        $this->assertStringContainsString("DB_PASSWORD='Kq7-zR2_mW9!xP4'", $env);
        $this->assertStringContainsString("DB_HOST='db'", $env);
        $this->assertMatchesRegularExpression("/^DB_ROOT_PASSWORD='[^']{24}'$/m", $env);
        // ほかの行はそのまま、同じキーは書き換えて増やさない
        $this->assertStringContainsString("APP_NAME=Biscuit\n", $env);
        $this->assertStringContainsString("MAIL_HOST=mailpit\n", $env);
        $this->assertSame(1, substr_count($env, 'DB_PASSWORD='));

        $state = app(InstallationState::class);
        $this->assertTrue($state->isCompleted(InstallerStep::Database));
        $this->assertSame('biscuit_site', $state->get('db_database'));
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidInputProvider(): array
    {
        return [
            '初期のパスワード' => [['password' => 'password', 'password_confirmation' => 'password'], 'password'],
            '大文字にした初期のパスワード' => [['password' => 'PASSWORD', 'password_confirmation' => 'PASSWORD'], 'password'],
            '短いパスワード' => [['password' => 'Kq7-zR2', 'password_confirmation' => 'Kq7-zR2'], 'password'],
            'ユーザー名と同じパスワード' => [['username' => 'biscuit_admin_user', 'password' => 'biscuit_admin_user', 'password_confirmation' => 'biscuit_admin_user'], 'password'],
            '.env で壊れる文字' => [['password' => 'Kq7-zR2$mW9!xP4', 'password_confirmation' => 'Kq7-zR2$mW9!xP4'], 'password'],
            '確認と違う' => [['password_confirmation' => 'Kq7-zR2_mW9!xP5'], 'password'],
            'root のユーザー' => [['username' => 'root'], 'username'],
            'DB 名に使えない文字' => [['database' => 'biscuit-site;'], 'database'],
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     */
    #[DataProvider('invalidInputProvider')]
    public function test_unsafe_input_cannot_proceed(array $overrides, string $field): void
    {
        $before = (string) file_get_contents($this->envPath);

        $this->from(route('installer.database'))
            ->post(route('installer.database.store'), $this->input($overrides))
            ->assertSessionHasErrors($field);

        $this->assertSame($before, (string) file_get_contents($this->envPath));
        $this->assertFalse(app(InstallationState::class)->isCompleted(InstallerStep::Database));
    }

    public function test_generated_password_is_shown_without_being_stored(): void
    {
        $response = $this->post(route('installer.database.generate'), ['database' => 'my_site', 'username' => 'my_user'])
            ->assertOk()
            ->assertSee('data-generated-password', false)
            ->assertSee('value="my_site"', false);

        preg_match('/<code class="user-select-all">([^<]+)<\/code>/', $response->getContent(), $matches);
        $password = html_entity_decode($matches[1]);
        $this->assertSame(24, strlen($password));

        // 生成したパスワードは画面に出すだけで、セッション・インストーラーの状態には残さない
        $this->assertNull(session('generated_password'));
        foreach (Storage::disk('local')->allFiles() as $file) {
            $this->assertStringNotContainsString($password, (string) Storage::disk('local')->get($file));
        }
    }

    public function test_generated_passwords_follow_the_policy(): void
    {
        foreach (range(1, 20) as $ignored) {
            $password = PasswordPolicy::generate();

            $this->assertSame(24, strlen($password));
            $this->assertDoesNotMatchRegularExpression(EnvironmentWriter::UNSAFE_VALUE_PATTERN, $password);
            $this->assertFalse(PasswordPolicy::isForbidden($password));
        }

        $this->assertNotSame(PasswordPolicy::generate(), PasswordPolicy::generate());
    }

    public function test_the_database_password_is_not_written_to_the_log(): void
    {
        $messages = [];
        Log::listen(function ($event) use (&$messages) {
            $messages[] = $event->message.' '.json_encode($event->context);
        });

        $this->post(route('installer.database.store'), $this->input())->assertRedirect();

        $this->assertNotEmpty($messages);
        foreach ($messages as $message) {
            $this->assertStringNotContainsString('Kq7-zR2_mW9!xP4', $message);
        }
    }

    public function test_the_step_requires_the_requirements_step(): void
    {
        Storage::fake('local');

        $this->get(route('installer.database'))->assertRedirect(route('installer.requirements'));
        $this->post(route('installer.database.store'), $this->input())->assertRedirect(route('installer.requirements'));
        $this->assertStringNotContainsString('biscuit_site', (string) file_get_contents($this->envPath));
    }

    public function test_environment_writer_only_changes_allowed_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new EnvironmentWriter($this->envPath))->set(['APP_KEY' => 'base64:x']);
    }
}
