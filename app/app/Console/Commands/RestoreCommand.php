<?php

namespace App\Console\Commands;

use App\Services\RestoreService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * バックアップから戻す(./biscuit restore が app コンテナの中で動かす。ホスト側がリストアの前のバックアップ・メンテナンスモード・
 * スケジューラーの停止を受け持つ)。--check は戻さずに、戻せるかだけを確かめる。
 * データベースを消して入れ直すため、キャッシュのロック(Isolatable)は使わない(同時に動かさないのはホスト側のロック)。
 */
#[Signature('biscuit:restore {name : バックアップの名前(biscuit:backup --list の「ファイル」)} {--check : 戻さずに、戻せるかだけを確かめる} {--force : 確かめずに戻す} {--json : 結果を JSON で出す}')]
#[Description('バックアップからデータベースと画像を戻す')]
class RestoreCommand extends Command
{
    public function handle(RestoreService $restore): int
    {
        $name = (string) $this->argument('name');

        try {
            $manifest = $restore->inspect($name);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('check')) {
            if ($this->option('json')) {
                $this->line((string) json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                $this->line("  {$name}: v{$manifest['version']}・{$manifest['created_at']}・{$manifest['database']['tables']} テーブル・{$manifest['files']['count']} ファイル");
            }

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("いまのデータベースと画像を、{$name} の内容に入れ替えます。よろしいですか?")) {
            return self::FAILURE;
        }

        try {
            $result = $restore->restore($name);
        } catch (Throwable $e) {
            report($e);
            $this->error('戻せませんでした: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->info("{$name} から戻しました");
        $this->line("  データベース: {$result['statements']} 文を実行".($result['migrated'] ? '(今の版に合わせてマイグレーションしました)' : ''));
        $this->line("  画像など:     {$result['files']} ファイル");
        $this->line('  <fg=gray>.env は戻していません(いまのサーバーの設定のまま)。</>');

        return self::SUCCESS;
    }
}
