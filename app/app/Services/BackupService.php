<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use FilesystemIterator;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * バックアップ(./biscuit backup・毎日のスケジュール・これからの更新の前)。データベース・画像(public ディスク)・.env を
 * 1 つの tar.gz にまとめ、local ディスクの backups/ に 0600 で置く(.env の秘密の値が入るため、www-data だけが読める)。
 * 本番の app イメージには mysqldump・zip がないため、データベースは PHP で SQL に書き出し、PharData で固める
 * (PharData は phar.readonly=1 でも書ける)。古いものは config('biscuit.backup.keep') 世代だけ残して消す。
 *
 * 中身: manifest.json(版・日時・理由・件数)・database.sql・env/.env・storage/public/**
 */
class BackupService
{
    /** バックアップの置き場所(local ディスクの中) */
    public const DIRECTORY = 'backups';

    /** manifest.json の形の版(リストアが読めるかの判断に使う) */
    public const FORMAT = 1;

    /** 作るときの理由(manifest.json と、ファイル名の末尾に入れる) */
    public const REASONS = ['manual', 'daily', 'pre-update'];

    /** 1 つの INSERT にまとめる行の数 */
    private const INSERT_ROWS = 100;

    /**
     * バックアップを作り、古いものを消す。
     *
     * @return array{name: string, path: string, size: int, manifest: array<string, mixed>, pruned: list<string>}
     */
    public function create(string $reason = 'manual'): array
    {
        if (! in_array($reason, self::REASONS, true)) {
            throw new RuntimeException("バックアップの理由 {$reason} は使えません。");
        }

        $createdAt = CarbonImmutable::now();
        $name = 'biscuit-'.$createdAt->format('Ymd-His').'-'.$reason;
        $directory = $this->directory();
        // PharData は同じプロセスで開いたアーカイブをパスで覚えているため、作業用のパスは毎回変える
        $work = $directory.'/.work-'.$name.'-'.bin2hex(random_bytes(4));
        $tar = $work.'/archive.tar';
        $archive = $directory.'/'.$name.'.tar.gz';

        if (File::exists($archive)) {
            throw new RuntimeException("同じ名前のバックアップ({$name})があります。少し待ってから作り直してください。");
        }

        // 作る途中のファイル(SQL・.env の写し)も、ほかの利用者に読ませない
        $umask = umask(0077);

        try {
            File::ensureDirectoryExists($work, 0700);
            $this->ensureFreeSpace($directory);

            $database = $this->dumpDatabase($work.'/database.sql');

            $phar = new PharData($tar);
            $phar->addFile($work.'/database.sql', 'database.sql');

            $env = base_path('.env');
            $hasEnv = is_file($env);
            if ($hasEnv) {
                $phar->addFile($env, 'env/.env');
            }

            $files = $this->addPublicFiles($phar);

            $manifest = [
                'format' => self::FORMAT,
                'version' => (string) config('biscuit.version'),
                'created_at' => $createdAt->toIso8601String(),
                'reason' => $reason,
                'database' => $database,
                'files' => $files,
                'env' => $hasEnv,
            ];
            $phar->addFromString('manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $phar->compress(Phar::GZ);
            unset($phar);
            File::move($tar.'.gz', $archive);
            chmod($archive, 0600);
        } catch (Throwable $e) {
            File::delete($archive);

            throw $e;
        } finally {
            umask($umask);
            File::deleteDirectory($work);
        }

        return [
            'name' => basename($archive),
            'path' => $archive,
            'size' => (int) filesize($archive),
            'manifest' => $manifest,
            'pruned' => $this->prune(),
        ];
    }

    /**
     * いまあるバックアップ(新しい順)。
     *
     * @return list<array{name: string, path: string, size: int, created_at: string, reason: string|null}>
     */
    public function list(): array
    {
        $directory = $this->directory(create: false);
        if (! is_dir($directory)) {
            return [];
        }

        $backups = [];
        foreach (glob($directory.'/biscuit-*.tar.gz') ?: [] as $path) {
            if (! preg_match('/^biscuit-(\d{8}-\d{6})-([a-z-]+)\.tar\.gz$/', basename($path), $matches)) {
                continue;
            }

            $backups[] = [
                'name' => basename($path),
                'path' => $path,
                'size' => (int) filesize($path),
                'created_at' => CarbonImmutable::createFromFormat('Ymd-His', $matches[1])->toIso8601String(),
                'reason' => in_array($matches[2], self::REASONS, true) ? $matches[2] : null,
            ];
        }

        // ファイル名の日時の新しい順(同じ秒はないため名前で並べればよい)
        usort($backups, fn (array $a, array $b) => strcmp($b['name'], $a['name']));

        return $backups;
    }

    /**
     * いちばん新しいバックアップ(なければ null)。
     *
     * @return array{name: string, path: string, size: int, created_at: string, reason: string|null}|null
     */
    public function latest(): ?array
    {
        return $this->list()[0] ?? null;
    }

    /**
     * 残す世代を超えた古いバックアップを消す。消したファイルの名前を返す。
     *
     * @return list<string>
     */
    public function prune(): array
    {
        $keep = max(1, (int) config('biscuit.backup.keep'));
        $pruned = [];

        foreach (array_slice($this->list(), $keep) as $backup) {
            File::delete($backup['path']);
            $pruned[] = $backup['name'];
        }

        return $pruned;
    }

    public function directory(bool $create = true): string
    {
        $directory = Storage::disk('local')->path(self::DIRECTORY);
        if ($create) {
            File::ensureDirectoryExists($directory, 0700);
        }

        return $directory;
    }

    /**
     * データベースのすべてのテーブルを、作り直して入れ直す SQL に書き出す。
     *
     * @return array{driver: string, tables: int, rows: int}
     */
    public function dumpDatabase(string $path): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException("データベース {$driver} のバックアップには対応していません。");
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("{$path} に書き込めません。");
        }

        $rows = 0;
        $tables = $this->tables($connection);

        try {
            fwrite($handle, '-- Biscuit v'.config('biscuit.version').' のバックアップ('.CarbonImmutable::now()->toIso8601String().")\n");
            if ($driver === 'sqlite') {
                fwrite($handle, "PRAGMA foreign_keys = OFF;\n");
            } else {
                // timestamp 型は接続のタイムゾーンで読み書きされるため、読んだときと同じタイムゾーンで入れ直す
                $timeZone = (string) $connection->selectOne('select @@session.time_zone as tz')->tz;
                fwrite($handle, "SET NAMES utf8mb4;\nSET time_zone = ".$connection->getPdo()->quote($timeZone).";\nSET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\n");
            }

            foreach ($tables as $table) {
                fwrite($handle, "\n-- {$table}\n");
                fwrite($handle, 'DROP TABLE IF EXISTS '.$connection->getQueryGrammar()->wrapTable($table).";\n");
                foreach ($this->createStatements($connection, $table) as $statement) {
                    fwrite($handle, $statement.";\n");
                }
                $rows += $this->dumpRows($connection, $table, $handle);
            }

            fwrite($handle, $driver === 'sqlite' ? "\nPRAGMA foreign_keys = ON;\n" : "\nSET UNIQUE_CHECKS = 1;\nSET FOREIGN_KEY_CHECKS = 1;\n");
        } finally {
            fclose($handle);
        }

        return ['driver' => $driver, 'tables' => count($tables), 'rows' => $rows];
    }

    /**
     * いまのデータベースのテーブル(カスタムページの実行時に作ったテーブルも含む)。
     *
     * @return list<string>
     */
    private function tables(Connection $connection): array
    {
        $schema = $connection->getDriverName() === 'sqlite' ? 'main' : $connection->getDatabaseName();
        $tables = Schema::connection($connection->getName())->getTableListing($schema, schemaQualified: false);
        sort($tables);

        return $tables;
    }

    /**
     * テーブルを作る SQL(SQLite はテーブルのあとにインデックスも)。
     *
     * @return list<string>
     */
    private function createStatements(Connection $connection, string $table): array
    {
        if ($connection->getDriverName() === 'sqlite') {
            return array_map(
                fn (object $row) => $row->sql,
                $connection->select("select sql from sqlite_master where tbl_name = ? and sql is not null order by type = 'index', name", [$table]),
            );
        }

        $row = (array) $connection->selectOne('SHOW CREATE TABLE '.$connection->getQueryGrammar()->wrapTable($table));

        return [(string) ($row['Create Table'] ?? array_values($row)[1])];
    }

    /**
     * テーブルの行を INSERT に書き出す(生成カラムは入れ直すと失敗するため書かない)。書き出した行の数を返す。
     *
     * @param  resource  $handle
     */
    private function dumpRows(Connection $connection, string $table, $handle): int
    {
        $builder = Schema::connection($connection->getName());
        $columns = array_values(array_map(
            fn (array $column) => $column['name'],
            array_filter($builder->getColumns($table), fn (array $column) => empty($column['generation'])),
        ));
        if ($columns === []) {
            return 0;
        }

        $grammar = $connection->getQueryGrammar();
        $prefix = 'INSERT INTO '.$grammar->wrapTable($table).' ('.implode(', ', array_map(fn ($column) => $grammar->wrap($column), $columns)).') VALUES ';

        // 主キーが 1 カラムなら、その順に少しずつ読む(大きいテーブルでもメモリに載せきらない)
        $primary = collect($builder->getIndexes($table))->first(fn (array $index) => $index['primary']);
        $query = $connection->table($table)->select($columns);
        $cursor = $primary !== null && count($primary['columns']) === 1 && in_array($primary['columns'][0], $columns, true)
            ? $query->lazyById(1000, $primary['columns'][0])
            : $query->cursor();

        $pdo = $connection->getPdo();
        $count = 0;
        $values = [];

        foreach ($cursor as $row) {
            $values[] = '('.implode(', ', array_map(
                fn ($value) => match (true) {
                    $value === null => 'NULL',
                    is_bool($value) => $value ? '1' : '0',
                    is_int($value), is_float($value) => (string) $value,
                    default => $pdo->quote((string) $value),
                },
                array_values((array) $row),
            )).')';
            $count++;

            if (count($values) >= self::INSERT_ROWS) {
                fwrite($handle, $prefix.implode(",\n", $values).";\n");
                $values = [];
            }
        }

        if ($values !== []) {
            fwrite($handle, $prefix.implode(",\n", $values).";\n");
        }

        return $count;
    }

    /**
     * 画像など公開のファイル(public ディスク)を storage/public/ に入れる。入れたファイルの数と大きさを返す。
     *
     * @return array{count: int, bytes: int}
     */
    private function addPublicFiles(PharData $phar): array
    {
        $root = Storage::disk('public')->path('');
        $count = 0;
        $bytes = 0;

        if (! is_dir($root)) {
            return ['count' => 0, 'bytes' => 0];
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            // シンボリックリンクはたどらない(外のファイルを入れない)。.gitignore などの隠しファイルは入れない
            if (! $file->isFile() || $file->isLink() || str_starts_with($file->getFilename(), '.')) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            $phar->addFile($file->getPathname(), 'storage/public/'.$relative);
            $count++;
            $bytes += $file->getSize();
        }

        return ['count' => $count, 'bytes' => $bytes];
    }

    /**
     * 画像の合計の 2 倍(.tar と .tar.gz を一度に置く)と 100MB の余裕がなければ止める。
     */
    private function ensureFreeSpace(string $directory): void
    {
        $free = @disk_free_space($directory);
        if ($free === false) {
            return;
        }

        $needed = $this->publicBytes() * 2 + 100 * 1024 * 1024;
        if ($free < $needed) {
            throw new RuntimeException(sprintf('ディスクの空きが足りません(空き %dMB、必要 %dMB)。', intdiv((int) $free, 1048576), intdiv($needed, 1048576)));
        }
    }

    private function publicBytes(): int
    {
        $root = Storage::disk('public')->path('');
        if (! is_dir($root)) {
            return 0;
        }

        $bytes = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && ! $file->isLink()) {
                $bytes += $file->getSize();
            }
        }

        return $bytes;
    }
}
