<?php

namespace App\Console\Commands;

use App\Installer\InstallerManager;
use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Throwable;

/**
 * バックアップを作る・一覧を出す(./biscuit backup が app コンテナの中で動かす。毎日のスケジュールも --reason=daily で動かす)。
 * 同時に 2 つ動かさない(Isolatable)。
 */
#[Signature('biscuit:backup {--reason=manual : 作る理由(manual・daily・pre-update)} {--list : 作らずに、いまあるバックアップを表示する} {--json : 結果を JSON で出す}')]
#[Description('データベース・画像・.env をまとめてバックアップする')]
class BackupCommand extends Command implements Isolatable
{
    public function handle(BackupService $backups, InstallerManager $installer): int
    {
        if ($this->option('list')) {
            return $this->showList($backups);
        }

        if (! $installer->isInstalled()) {
            $this->error('Biscuit はまだインストールしていません。');

            return self::FAILURE;
        }

        $reason = (string) $this->option('reason');
        if (! in_array($reason, BackupService::REASONS, true)) {
            $this->error('--reason は '.implode('・', BackupService::REASONS).' のどれかにしてください。');

            return self::INVALID;
        }

        try {
            $result = $backups->create($reason);
        } catch (Throwable $e) {
            report($e);
            $this->error('バックアップを作れませんでした: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $manifest = $result['manifest'];
        $this->components->info("バックアップを作りました: {$result['name']}(".$this->size($result['size']).')');
        $this->line("  データベース: {$manifest['database']['tables']} テーブル・{$manifest['database']['rows']} 行");
        $this->line("  画像など:     {$manifest['files']['count']} ファイル");
        $this->line('  置き場所:     '.$this->relative($result['path']));
        foreach ($result['pruned'] as $name) {
            $this->line("  <fg=gray>古いバックアップを消しました: {$name}</>");
        }
        $this->line('  <fg=yellow>.env(パスワード・APP_KEY)が入っています。サーバーの外へ写すときは、人に見られない場所に置いてください。</>');

        return self::SUCCESS;
    }

    private function showList(BackupService $backups): int
    {
        $list = $backups->list();

        if ($this->option('json')) {
            $this->line((string) json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($list === []) {
            $this->line('  バックアップはまだありません。');

            return self::SUCCESS;
        }

        $this->table(['ファイル', '日時', '理由', '大きさ'], array_map(fn (array $backup) => [
            $backup['name'],
            str_replace('T', ' ', substr($backup['created_at'], 0, 19)),
            match ($backup['reason']) {
                'daily' => '毎日',
                'pre-update' => '更新の前',
                'manual' => '手動',
                default => '-',
            },
            $this->size($backup['size']),
        ], $list));
        $this->line('  置き場所: '.$this->relative($backups->directory(create: false)).'(新しい '.config('biscuit.backup.keep').' 個を残します)');

        return self::SUCCESS;
    }

    /**
     * ファイルの大きさ(本番の PHP には intl がないため、Number::fileSize() を使わない)
     */
    private function size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) $bytes : number_format($value, 1)).$units[$unit];
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }
}
