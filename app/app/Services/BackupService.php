<?php

namespace App\Services;

use App\Support\Backup\DatabaseDump;
use Carbon\CarbonImmutable;
use FilesystemIterator;
use Generator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * バックアップ(./biscuit backup・毎日のスケジュール・更新の前(./biscuit update))。データベース・画像(public ディスク)・.env を
 * 1 つの tar.gz にまとめ、local ディスクの backups/ に 0600 で置く(.env の秘密の値が入るため、www-data だけが読める)。
 * 本番の app イメージには mysqldump・zip がないため、データベースは DatabaseDump で SQL に書き出し、PharData で固める
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
    public const REASONS = ['manual', 'daily', 'pre-update', 'pre-restore', 'pre-down'];

    /** バックアップのファイル名(日時・理由)。./biscuit の BACKUP_NAME_PATTERN と同じ形 */
    public const NAME_PATTERN = '/^biscuit-(\d{8}-\d{6})-([a-z-]+)\.tar\.gz$/';

    /**
     * バックアップを作り、古いものを消す($prune が false なら消さない。リストアの前に作るとき、戻す対象を消さないため)。
     *
     * @return array{name: string, path: string, size: int, manifest: array<string, mixed>, pruned: list<string>}
     */
    public function create(string $reason = 'manual', bool $prune = true): array
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

            $database = (new DatabaseDump)->export($work.'/database.sql');

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
            'pruned' => $prune ? $this->prune() : [],
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
            if (! preg_match(self::NAME_PATTERN, basename($path), $matches)) {
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

    /**
     * 名前からバックアップのファイルを探す(決まった形の名前だけを受け付け、置き場所の外を読ませない)。
     */
    public function find(string $name): string
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            throw new RuntimeException("バックアップの名前ではありません: {$name}");
        }

        $path = $this->directory(create: false).'/'.$name;
        if (! is_file($path)) {
            throw new RuntimeException("{$name} が見つかりません(./biscuit backup list で名前を確かめてください)。");
        }

        return $path;
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
     * 画像など公開のファイル(public ディスク)を storage/public/ に入れる。入れたファイルの数と大きさを返す。
     *
     * @return array{count: int, bytes: int}
     */
    private function addPublicFiles(PharData $phar): array
    {
        $count = 0;
        $bytes = 0;

        foreach ($this->publicFiles() as $relative => $file) {
            $phar->addFile($file->getPathname(), 'storage/public/'.$relative);
            $count++;
            $bytes += $file->getSize();
        }

        return ['count' => $count, 'bytes' => $bytes];
    }

    /**
     * バックアップに入れる公開のファイル(public ディスクからの相対パス => ファイル)。シンボリックリンクはたどらず
     * (外のファイルを入れない)、.gitignore などの隠しファイルは入れない。
     *
     * @return Generator<string, SplFileInfo>
     */
    private function publicFiles(): Generator
    {
        $root = Storage::disk('public')->path('');
        if (! is_dir($root)) {
            return;
        }

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && ! $file->isLink() && ! str_starts_with($file->getFilename(), '.')) {
                yield ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/') => $file;
            }
        }
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

        $bytes = 0;
        foreach ($this->publicFiles() as $file) {
            $bytes += $file->getSize();
        }

        $needed = $bytes * 2 + 100 * 1024 * 1024;
        if ($free < $needed) {
            throw new RuntimeException(sprintf('ディスクの空きが足りません(空き %dMB、必要 %dMB)。', intdiv((int) $free, 1048576), intdiv($needed, 1048576)));
        }
    }
}
