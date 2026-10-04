<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\Article;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PharData;
use Tests\TestCase;

/**
 * バックアップ(biscuit:backup)。データベース・画像・.env を 1 つの tar.gz にまとめ、古いものを消す。
 */
class BackupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Http::fake(['http://front:3000*' => Http::response('ok')]);
        config(['biscuit.update_check.enabled' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function backup(string $reason = 'manual'): array
    {
        $status = Artisan::call('biscuit:backup', ['--reason' => $reason, '--json' => true]);
        $output = Artisan::output();
        $this->assertSame(0, $status, $output);

        return json_decode(trim($output), true);
    }

    /**
     * tar.gz を作業用のディレクトリに展開する。
     */
    private function extract(string $path): string
    {
        $directory = sys_get_temp_dir().'/biscuit-backup-test-'.uniqid();
        (new PharData($path))->extractTo($directory);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($directory));

        return $directory;
    }

    public function test_it_archives_the_database_images_and_env(): void
    {
        Administrator::factory()->create();
        Article::factory()->count(3)->create();
        Storage::disk('public')->put('image/thumbnail/a.jpg', 'jpeg');

        $result = $this->backup();

        $this->assertMatchesRegularExpression('/^biscuit-\d{8}-\d{6}-manual\.tar\.gz$/', $result['name']);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($result['path'])), -4));

        $directory = $this->extract($result['path']);
        $manifest = json_decode((string) file_get_contents($directory.'/manifest.json'), true);
        $this->assertSame(BackupService::FORMAT, $manifest['format']);
        $this->assertSame(config('biscuit.version'), $manifest['version']);
        $this->assertSame('manual', $manifest['reason']);
        $this->assertSame(1, $manifest['files']['count']);
        $this->assertSame('jpeg', file_get_contents($directory.'/storage/public/image/thumbnail/a.jpg'));
        $this->assertFileExists($directory.'/database.sql');
        $this->assertSame(is_file(base_path('.env')), is_file($directory.'/env/.env'));

        // 作業用のファイルを残さない
        $this->assertSame([basename($result['path'])], array_map('basename', File::files(dirname($result['path']))));
        $this->assertSame([], File::directories(dirname($result['path'])));
    }

    public function test_the_dump_restores_the_same_rows(): void
    {
        Administrator::factory()->create(['email' => "o'brien@example.com"]);
        $user = User::factory()->create();
        $user->delete();
        Article::factory()->count(250)->create();

        $sql = Storage::disk('local')->path('dump.sql');
        $summary = app(BackupService::class)->dumpDatabase($sql);

        $this->assertSame('sqlite', $summary['driver']);
        $this->assertSame(250, DB::table('articles')->count());

        // 別のデータベースに入れ直して、行と生成カラムが同じになるか
        config(['database.connections.restore' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::connection('restore')->getPdo()->exec((string) file_get_contents($sql));

        foreach (['articles', 'administrators', 'users', 'migrations'] as $table) {
            $this->assertSame(DB::table($table)->count(), DB::connection('restore')->table($table)->count(), $table);
        }
        $this->assertSame("o'brien@example.com", DB::connection('restore')->table('administrators')->value('email'));
        // 論理削除した行は unique_email(生成カラム)が null に計算し直される
        $this->assertNull(DB::connection('restore')->table('users')->where('id', $user->id)->value('unique_email'));
        $this->assertSame($summary['rows'], collect(DB::connection('restore')->getSchemaBuilder()->getTableListing('main', false))
            ->sum(fn (string $table) => DB::connection('restore')->table($table)->count()));
    }

    public function test_it_keeps_only_the_newest_backups(): void
    {
        config(['biscuit.backup.keep' => 2]);
        $directory = app(BackupService::class)->directory();
        foreach (['20260101-030000-daily', '20260102-030000-daily', '20260103-030000-manual'] as $name) {
            File::put("{$directory}/biscuit-{$name}.tar.gz", 'old');
        }
        File::put("{$directory}/memo.txt", 'ほかのファイルは消さない');

        $result = $this->backup('daily');

        $this->assertSame(['biscuit-20260102-030000-daily.tar.gz', 'biscuit-20260101-030000-daily.tar.gz'], $result['pruned']);
        $this->assertSame(
            [$result['name'], 'biscuit-20260103-030000-manual.tar.gz'],
            array_column(app(BackupService::class)->list(), 'name'),
        );
        $this->assertFileExists("{$directory}/memo.txt");
    }

    public function test_it_lists_the_backups(): void
    {
        $this->artisan('biscuit:backup --list')->expectsOutputToContain('バックアップはまだありません')->assertSuccessful();

        $result = $this->backup('pre-update');

        $this->assertSame(0, Artisan::call('biscuit:backup', ['--list' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString($result['name'], $output);
        $this->assertStringContainsString('更新の前', $output);
    }

    public function test_it_rejects_an_unknown_reason(): void
    {
        $this->artisan('biscuit:backup --reason=weekly')->assertExitCode(2);
        $this->assertSame([], app(BackupService::class)->list());
    }

    public function test_it_does_not_run_before_the_installation(): void
    {
        config(['installer.assume_installed' => false]);

        $this->artisan('biscuit:backup')->assertFailed();
        $this->assertSame([], app(BackupService::class)->list());
    }

    public function test_doctor_and_status_show_the_latest_backup(): void
    {
        Administrator::factory()->create();

        Artisan::call('biscuit:doctor', ['--offline' => true, '--json' => true]);
        $check = collect(json_decode(trim(Artisan::output()), true)['checks'])->firstWhere('key', 'backup');
        $this->assertFalse($check['ok']);

        $this->backup();

        Artisan::call('biscuit:doctor', ['--offline' => true, '--json' => true]);
        $check = collect(json_decode(trim(Artisan::output()), true)['checks'])->firstWhere('key', 'backup');
        $this->assertTrue($check['ok']);

        Artisan::call('biscuit:status', ['--json' => true]);
        $this->assertNotNull(json_decode(trim(Artisan::output()), true)['latest_backup']);
    }
}
