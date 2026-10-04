<?php

namespace App\Services;

use App\Support\Backup\DatabaseDump;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PharData;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * バックアップ(BackupService が作った tar.gz)から、データベースと画像(public ディスク)を戻す(./biscuit restore が
 * app コンテナの中で biscuit:restore として動かす)。いまのテーブル・画像はすべて入れ替える。
 * .env は戻さない(DB のパスワード・URL はいまのサーバーの値が正しいため。バックアップの env/.env は手で見るためのもの)。
 * 古い版のバックアップは、戻したあとにマイグレーションで今の版に合わせる。新しい版のバックアップは、先に更新が要るため戻さない。
 */
class RestoreService
{
    public function __construct(private BackupService $backups) {}

    /**
     * バックアップの中身を確かめ、manifest.json を返す(戻さない)。
     *
     * @return array<string, mixed>
     */
    public function inspect(string $name): array
    {
        $path = $this->backups->find($name);

        try {
            $phar = new PharData($path);
        } catch (Throwable $e) {
            throw new RuntimeException("{$name} を開けません(壊れているか、バックアップのファイルではありません)。", previous: $e);
        }

        $entries = [];
        foreach (new RecursiveIteratorIterator($phar) as $file) {
            $entries[] = substr($file->getPathname(), strlen('phar://'.$path.'/'));
        }

        foreach ($entries as $entry) {
            // 決まった場所のファイルだけを受け付ける(.. で外へ書かせない)
            $allowed = in_array($entry, ['manifest.json', 'database.sql', 'env/.env'], true)
                || (str_starts_with($entry, 'storage/public/') && ! in_array('..', explode('/', $entry), true));
            if (! $allowed) {
                throw new RuntimeException("{$name} に、バックアップにないはずのファイル({$entry})があります。");
            }
        }

        if (! in_array('manifest.json', $entries, true) || ! in_array('database.sql', $entries, true)) {
            throw new RuntimeException("{$name} に manifest.json・database.sql がありません。");
        }

        $manifest = json_decode((string) $phar['manifest.json']->getContent(), true);
        if (! is_array($manifest) || ($manifest['format'] ?? null) !== BackupService::FORMAT) {
            throw new RuntimeException("{$name} は、この版の Biscuit では読めない形です。");
        }

        $current = (string) config('biscuit.version');
        if (version_compare((string) ($manifest['version'] ?? '0'), $current, '>')) {
            throw new RuntimeException("{$name} は新しい版(v{$manifest['version']})のバックアップです。先に Biscuit を v{$manifest['version']} 以上に更新してください(いまは v{$current})。");
        }

        $driver = DB::connection()->getDriverName();
        if (($manifest['database']['driver'] ?? null) !== $driver) {
            throw new RuntimeException("{$name} は {$manifest['database']['driver']} のバックアップのため、{$driver} には戻せません。");
        }

        return $manifest;
    }

    /**
     * バックアップから戻す。
     *
     * @return array{manifest: array<string, mixed>, statements: int, files: int, migrated: bool}
     */
    public function restore(string $name): array
    {
        $manifest = $this->inspect($name);
        $work = $this->backups->directory().'/.restore-'.bin2hex(random_bytes(4));
        $umask = umask(0077);

        try {
            (new PharData($this->backups->find($name)))->extractTo($work);

            $statements = (new DatabaseDump)->import($work.'/database.sql');
            $files = $this->replacePublicFiles($work.'/storage/public');

            // 古い版のバックアップを今の版のテーブルに合わせる
            Artisan::call('migrate', ['--force' => true]);
            $migrated = ! str_contains(Artisan::output(), 'Nothing to migrate');

            Cache::flush();
        } finally {
            umask($umask);
            File::deleteDirectory($work);
        }

        return ['manifest' => $manifest, 'statements' => $statements, 'files' => $files, 'migrated' => $migrated];
    }

    /**
     * public ディスクの中身を、バックアップの storage/public と入れ替える(隠しファイルは残す)。戻したファイルの数を返す。
     */
    private function replacePublicFiles(string $source): int
    {
        $disk = Storage::disk('public');
        $root = $disk->path('');
        File::ensureDirectoryExists($root);

        foreach (File::directories($root) as $directory) {
            File::deleteDirectory($directory);
        }
        foreach (File::files($root) as $file) {
            File::delete($file->getPathname());
        }

        if (! is_dir($source)) {
            return 0;
        }

        // 画像は php-fpm(www-data)が読み書きするため、ふつうの権限に戻す
        $umask = umask(0022);
        try {
            File::copyDirectory($source, $root);
        } finally {
            umask($umask);
        }

        return count(File::allFiles($root));
    }
}
