<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;

/**
 * 管理画面のダッシュボードに出す、システム情報(バージョン・ディスク・ログ)と警告をまとめる。
 * 正常なら情報だけを小さく表示し、警告があるときだけ目立たせる。
 */
class SystemStatusService
{
    /**
     * エラーを数えるとき、ログファイルの末尾から読む最大のバイト数(大きなログでも重くならないようにする)。
     */
    public const LOG_READ_BYTES = 2 * 1024 * 1024;

    /**
     * エラーとして数えるログのレベル。
     */
    private const ERROR_LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    private readonly string $logDirectory;

    /**
     * @param  string|null  $logDirectory  ログファイルを置くディレクトリ(省略時は storage/logs。テストで差し替える)
     */
    public function __construct(?string $logDirectory = null)
    {
        $this->logDirectory = $logDirectory ?? storage_path('logs');
    }

    /**
     * バージョン・環境・ディスクの空き・ログの容量を返す。
     *
     * @return array{biscuit_version: string, laravel_version: string, php_version: string, environment: string, debug: bool, disk_free: int|null, disk_total: int|null, log_bytes: int}
     */
    public function info(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        return [
            'biscuit_version' => (string) config('biscuit.version'),
            'laravel_version' => Application::VERSION,
            'php_version' => PHP_VERSION,
            'environment' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'disk_free' => $free === false ? null : (int) $free,
            'disk_total' => $total === false ? null : (int) $total,
            'log_bytes' => array_sum(array_map(fn (string $file) => (int) @filesize($file), $this->logFiles())),
        ];
    }

    /**
     * 直近 config('biscuit.error_log_hours') 時間にログへ出たエラー(ERROR 以上)の件数と、最後のエラーの日時・メッセージ。
     * 数えるのは今の環境(APP_ENV)の行だけ(同じログファイルにテストなどほかの環境の行が入っていても数えない)。
     *
     * @return array{count: int, last_at: CarbonImmutable|null, last_message: string|null}
     */
    public function recentErrors(): array
    {
        $since = CarbonImmutable::now()->subHours((int) config('biscuit.error_log_hours'));
        $pattern = '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\] '.preg_quote(app()->environment(), '/').'\.('.implode('|', self::ERROR_LEVELS).'): (.*)$/m';
        $count = 0;
        $lastAt = null;
        $lastMessage = null;

        foreach ($this->logFiles() as $file) {
            if (@filemtime($file) < $since->getTimestamp()) {
                continue;
            }

            preg_match_all($pattern, $this->readTail($file), $matches, PREG_SET_ORDER);

            foreach ($matches as [, $datetime, , $message]) {
                $at = CarbonImmutable::parse($datetime);

                if ($at->lt($since)) {
                    continue;
                }

                $count++;

                if ($lastAt === null || $at->gte($lastAt)) {
                    $lastAt = $at;
                    $lastMessage = mb_strimwidth(trim($message), 0, 200, '…');
                }
            }
        }

        return ['count' => $count, 'last_at' => $lastAt, 'last_message' => $lastMessage];
    }

    /**
     * 対応が必要な状態の一覧(level は Bootstrap の色。danger はすぐに対応が必要なもの)。問題がなければ空。
     *
     * @param  array{biscuit_version: string, laravel_version: string, php_version: string, environment: string, debug: bool, disk_free: int|null, disk_total: int|null, log_bytes: int}  $info
     * @param  array{count: int, last_at: CarbonImmutable|null, last_message: string|null}  $errors
     * @return list<array{level: string, message: string}>
     */
    public function warnings(array $info, array $errors): array
    {
        $warnings = [];

        if ($info['disk_free'] !== null && $info['disk_total']) {
            $freePercent = $info['disk_free'] / $info['disk_total'] * 100;

            if ($freePercent < (int) config('biscuit.disk_free_warning_percent')) {
                $warnings[] = ['level' => 'danger', 'message' => __('ディスクの空きが残り :percent% です。', ['percent' => number_format($freePercent, 1)])];
            }
        }

        foreach ([storage_path('framework'), storage_path('app/public'), base_path('bootstrap/cache')] as $directory) {
            if (is_dir($directory) && ! is_writable($directory)) {
                $warnings[] = ['level' => 'danger', 'message' => __(':path に書き込めません。所有者と権限を確認してください。', ['path' => str_replace(base_path().'/', '', $directory)])];
            }
        }

        if ($errors['count'] > 0) {
            $warnings[] = ['level' => 'danger', 'message' => __('直近 :hours 時間にエラーが :count 件発生しています。', ['hours' => (int) config('biscuit.error_log_hours'), 'count' => $errors['count']])];
        }

        if ($info['debug'] && app()->isProduction()) {
            $warnings[] = ['level' => 'warning', 'message' => __('本番環境でデバッグモード(APP_DEBUG)が有効です。')];
        }

        if (! file_exists(public_path('storage'))) {
            $warnings[] = ['level' => 'warning', 'message' => __('アップロード画像の公開用のリンク(public/storage)がありません。php artisan storage:link を実行してください。')];
        }

        return $warnings;
    }

    /**
     * ログのディレクトリにあるログファイル(single・daily のどちらの形式も)。
     *
     * @return list<string>
     */
    private function logFiles(): array
    {
        return glob($this->logDirectory.'/*.log') ?: [];
    }

    /**
     * ログファイルの末尾 LOG_READ_BYTES バイトを読む。
     */
    private function readTail(string $file): string
    {
        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            return '';
        }

        $size = (int) filesize($file);
        fseek($handle, max(0, $size - self::LOG_READ_BYTES));
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }
}
