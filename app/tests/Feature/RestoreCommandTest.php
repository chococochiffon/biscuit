<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\Article;
use App\Services\BackupService;
use App\Support\Backup\SqlStatementReader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PharData;
use Tests\TestCase;

/**
 * バックアップから戻す(biscuit:restore)。テーブルを消して作り直すため、RefreshDatabase(トランザクション)を使わず、
 * テストごとに新しいインメモリの DB をマイグレーションする(DatabaseMigrations の後片付けのロールバックは SQLite で失敗するため使わない)。
 */
class RestoreCommandTest extends TestCase
{
    private static int $runs = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate');

        // PharData は同じプロセスで開いたアーカイブの中身をパスで覚えているため、テストごとに時刻(バックアップの名前)をずらす(BackupCommandTest の分単位と重ならないよう時間単位)
        $this->travelTo(now()->addHours(++self::$runs));

        Storage::fake('local');
        Storage::fake('public');
        Http::fake();
        config(['biscuit.update_check.enabled' => false]);
    }

    private function backup(): string
    {
        return app(BackupService::class)->create()['name'];
    }

    public function test_it_restores_the_database_and_the_images(): void
    {
        $administrator = Administrator::factory()->create(['name' => "管理者 'A'; \\ テスト"]);
        Article::factory()->count(3)->create();
        Storage::disk('public')->put('image/thumbnail/a.jpg', 'before');
        DB::table('cache_locks')->insert(['key' => 'framework/command-biscuit:backup', 'owner' => 'x', 'expiration' => time() + 3600]);
        $name = $this->backup();

        // バックアップのあとの変更(戻すと消える)
        Article::factory()->count(2)->create();
        $administrator->update(['name' => '変えた名前']);
        Storage::disk('public')->put('image/thumbnail/a.jpg', 'after');
        Storage::disk('public')->put('image/thumbnail/new.jpg', 'new');
        Schema::create('user_make_later', fn (Blueprint $table) => $table->id());

        $this->artisan('biscuit:restore', ['name' => $name, '--force' => true])
            ->expectsOutputToContain('から戻しました')
            ->assertSuccessful();

        $this->assertSame(3, Article::query()->count());
        $this->assertSame("管理者 'A'; \\ テスト", $administrator->fresh()->name);
        $this->assertSame('before', Storage::disk('public')->get('image/thumbnail/a.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('image/thumbnail/new.jpg'));
        $this->assertFalse(Schema::hasTable('user_make_later'));
        // キャッシュのロックは戻さない(バックアップのコマンドが動いている途中のまま止まらない)
        $this->assertSame(0, DB::table('cache_locks')->count());
        $this->assertNotNull(DB::table('administrators')->value('unique_email'));
    }

    public function test_check_does_not_change_anything(): void
    {
        Article::factory()->create();
        $name = $this->backup();
        Article::factory()->create();

        $this->artisan('biscuit:restore', ['name' => $name, '--check' => true])
            ->expectsOutputToContain('v'.config('biscuit.version'))
            ->assertSuccessful();

        $this->assertSame(2, Article::query()->count());
    }

    public function test_it_asks_before_restoring(): void
    {
        $name = $this->backup();
        Article::factory()->create();

        $this->artisan('biscuit:restore', ['name' => $name])
            ->expectsConfirmation("いまのデータベースと画像を、{$name} の内容に入れ替えます。よろしいですか?", 'no')
            ->assertFailed();

        $this->assertSame(1, Article::query()->count());
    }

    public function test_it_refuses_a_backup_of_a_newer_version(): void
    {
        config(['biscuit.version' => '9.0.0']);
        $name = $this->backup();
        config(['biscuit.version' => '1.0.0']);

        $this->artisan('biscuit:restore', ['name' => $name, '--force' => true])
            ->expectsOutputToContain('先に Biscuit を v9.0.0 以上に更新してください')
            ->assertFailed();
    }

    public function test_it_refuses_unknown_names_and_unexpected_files(): void
    {
        $this->artisan('biscuit:restore', ['name' => '../../.env', '--force' => true])
            ->expectsOutputToContain('バックアップの名前ではありません')
            ->assertFailed();

        $this->artisan('biscuit:restore', ['name' => 'biscuit-20260101-030000-manual.tar.gz', '--force' => true])
            ->expectsOutputToContain('見つかりません')
            ->assertFailed();

        $directory = app(BackupService::class)->directory();
        $phar = new PharData($directory.'/evil.tar');
        $phar->addFromString('manifest.json', '{"format":1}');
        $phar->addFromString('database.sql', '');
        $phar->addFromString('public/index.php', '<?php');
        $phar->compress(\Phar::GZ);
        unset($phar);
        File::move($directory.'/evil.tar.gz', $directory.'/biscuit-20260101-030000-manual.tar.gz');

        $this->artisan('biscuit:restore', ['name' => 'biscuit-20260101-030000-manual.tar.gz', '--force' => true])
            ->expectsOutputToContain('バックアップにないはずのファイル(public/index.php)')
            ->assertFailed();
    }

    public function test_the_reader_splits_statements_outside_quotes(): void
    {
        $path = Storage::disk('local')->path('statements.sql');
        File::put($path, implode("\n", [
            '-- コメント; は読み飛ばす',
            'CREATE TABLE `a;b` (x text);',
            "INSERT INTO t VALUES ('1;\n2', 'it\\'s', 'back\\\\'), (\"q;\");",
            "INSERT INTO t VALUES ('o''brien;');",
            'SELECT 1',
        ]));

        $this->assertSame([
            'CREATE TABLE `a;b` (x text)',
            "INSERT INTO t VALUES ('1;\n2', 'it\\'s', 'back\\\\'), (\"q;\")",
            "INSERT INTO t VALUES ('o''brien;')",
            'SELECT 1',
        ], iterator_to_array(SqlStatementReader::read($path, backslashEscapes: true), false));

        // SQLite の文字列ではバックスラッシュはただの文字
        File::put($path, "INSERT INTO t VALUES ('a\\'); SELECT 2;");
        $this->assertSame(["INSERT INTO t VALUES ('a\\')", 'SELECT 2'], iterator_to_array(SqlStatementReader::read($path, backslashEscapes: false), false));
    }

    public function test_backup_list_labels_the_pre_restore_backup(): void
    {
        app(BackupService::class)->create('pre-restore');

        Artisan::call('biscuit:backup', ['--list' => true]);
        $this->assertStringContainsString('リストアの前', Artisan::output());
    }
}
