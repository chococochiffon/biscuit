<?php

namespace App\Services;

use App\Support\Backup\DatabaseDump;
use App\Support\Backup\Gzip;
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
        return $this->withWork(fn (string $work) => $this->inspectArchive($name, $this->open($name, $work)));
    }

    /**
     * バックアップから戻す。
     *
     * @return array{manifest: array<string, mixed>, statements: int, files: int, migrated: bool}
     */
    public function restore(string $name): array
    {
        return $this->withWork(function (string $work) use ($name) {
            $phar = $this->open($name, $work);
            $manifest = $this->inspectArchive($name, $phar);
            $phar->extractTo($work.'/files');

            $statements = (new DatabaseDump)->import($work.'/files/database.sql');
            $files = $this->replacePublicFiles($work.'/files/storage/public');

            // 古い版のバックアップを今の版のテーブルに合わせる
            Artisan::call('migrate', ['--force' => true]);
            $migrated = ! str_contains(Artisan::output(), 'Nothing to migrate');

            Cache::flush();

            return ['manifest' => $manifest, 'statements' => $statements, 'files' => $files, 'migrated' => $migrated];
        });
    }

    /**
     * 作業用のフォルダ(バックアップの置き場所の中。ほかの利用者に読ませない)を作って渡し、終わったら消す。
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    private function withWork(callable $callback): mixed
    {
        $work = $this->backups->directory().'/.restore-'.bin2hex(random_bytes(4));
        $umask = umask(0077);

        try {
            File::ensureDirectoryExists($work, 0700);

            return $callback($work);
        } finally {
            umask($umask);
            File::deleteDirectory($work);
        }
    }

    /**
     * バックアップを作業用のフォルダへ tar に展開して開く(tar.gz を PharData で直接開くと、中身を丸ごとメモリに載せて落ちるため)。
     */
    private function open(string $name, string $work): PharData
    {
        $path = $this->backups->find($name);
        $tar = $work.'/archive.tar';

        try {
            Gzip::decompress($path, $tar);

            return new PharData($tar);
        } catch (Throwable $e) {
            throw new RuntimeException("{$name} を開けません(壊れているか、バックアップのファイルではありません)。", previous: $e);
        }
    }

    /**
     * 開いたバックアップの中身を確かめ、manifest.json を返す。
     *
     * @return array<string, mixed>
     */
    private function inspectArchive(string $name, PharData $phar): array
    {
        $root = 'phar://'.$phar->getPath().'/';
        $entries = [];
        foreach (new RecursiveIteratorIterator($phar) as $file) {
            $entries[] = substr($file->getPathname(), strlen($root));
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
