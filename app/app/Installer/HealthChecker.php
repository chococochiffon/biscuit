<?php

namespace App\Installer;

use App\Models\Administrator;
use App\Models\PageBuilder;
use App\Models\SiteSetting;
use App\Services\SystemStatusService;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * インストールの確認(Health Check)。完了の段と biscuit:install --status で使う。
 * - 必須(required): 満たさないと完了できない(DB・マイグレーション・サイト・管理者・デザイン・APP_KEY・書き込みの権限)
 * - 推奨(recommended): 警告だけ出して完了はできる(公開側に届くか・スケジューラー・メール・本番の設定・HTTPS・ディスク・PHP)
 * 確かめるときの例外は失敗として扱い、確認の画面そのものは必ず出す。
 */
class HealthChecker
{
    public const SCHEDULER_HEARTBEAT = 'scheduler-heartbeat';

    public function __construct(
        private InstallationState $state,
        private RequirementChecker $requirements,
        private SystemStatusService $system,
    ) {}

    /**
     * @return list<array{key: string, label: string, level: 'required'|'recommended', ok: bool, detail: string|null}>
     */
    public function check(): array
    {
        $databaseOk = (bool) $this->safely(fn () => DB::connection()->getPdo() !== null);
        $setting = $databaseOk ? $this->safely(fn () => SiteSetting::current()) : null;
        $administrator = $databaseOk ? $this->safely(fn () => Administrator::query()->orderBy('id')->first()) : null;
        $pending = $databaseOk ? $this->safely(fn () => $this->pendingMigrations()) : null;

        return [
            $this->required('database', __('データベースに接続できる'), $databaseOk),
            $this->required('migrations', __('テーブルを用意した(マイグレーション)'), $pending === 0, $pending ? __('未実行のマイグレーション: :count 件', ['count' => $pending]) : null),
            $this->required('site', __('サイト'), $setting instanceof SiteSetting, $setting?->site_title),
            $this->required('administrator', __('管理者'), $administrator instanceof Administrator, $administrator?->email),
            $this->required('design', __('デザイン'), $databaseOk && $this->safely(fn () => PageBuilder::top()?->isPublished() === true)),
            $this->required('app_key', __('暗号化の鍵(APP_KEY)がある'), filled(config('app.key'))),
            $this->required('writable', __('storage・bootstrap/cache に書き込める'), is_writable(storage_path()) && is_writable(base_path('bootstrap/cache'))),

            $this->recommended('front', __('公開側のサイトに届く'), $this->frontReachable(), (string) config('installer.front_internal_url')),
            $this->recommended('scheduler', __('スケジューラーが動いている'), $this->schedulerRunning(), __('更新の確認・お知らせ・予約公開のメールなどに使います。')),
            $this->recommended('mail', __('メールを設定した'), $this->state->isCompleted(InstallerStep::Mail), config('mail.mailers.smtp.host')),
            $this->recommended('storage_link', __('画像の公開用のリンク(public/storage)がある'), is_dir(public_path('storage'))),
            $this->recommended('debug', __('デバッグの表示が無効(APP_DEBUG=false)'), ! config('app.debug')),
            $this->recommended('environment', __('本番の設定(APP_ENV=production)'), app()->environment('production'), app()->environment()),
            $this->recommended('https', __('HTTPS で公開する'), $this->usesHttps(), __('HTTPS は外側のリバースプロキシで割り当ててください。')),
            $this->recommended('disk', __('ディスクの空きが十分にある'), $this->diskHasSpace(), $this->diskDetail()),
            $this->recommended('php', __('PHP のバージョンと拡張機能'), RequirementChecker::passes($this->requirements->check()), PHP_VERSION),
        ];
    }

    /**
     * 必須の項目をすべて満たしているか(完了できるか)。
     *
     * @param  list<array{level: string, ok: bool}>  $checks
     */
    public static function passes(array $checks): bool
    {
        return array_filter($checks, fn (array $check) => $check['level'] === 'required' && ! $check['ok']) === [];
    }

    /**
     * スケジューラーが毎分置く合図(routes/console.php)。最後の合図の時刻で、スケジューラーが動いているかを見る。
     */
    public static function beat(): void
    {
        Storage::disk('local')->put(self::SCHEDULER_HEARTBEAT, now()->toIso8601String());
    }

    private function pendingMigrations(): int
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));

        return count(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
    }

    private function frontReachable(): bool
    {
        // インストール中の公開側は「準備中」を 503 で返すため、503 も届いたとみなす(届かなければ接続の例外で失敗)
        return (bool) $this->safely(function () {
            $status = Http::timeout(5)->get((string) config('installer.front_internal_url'))->status();

            return $status < 500 || $status === 503;
        });
    }

    private function schedulerRunning(): bool
    {
        return (bool) $this->safely(function () {
            $beat = Storage::disk('local')->get(self::SCHEDULER_HEARTBEAT);

            return $beat !== null && Carbon::parse($beat)->gt(now()->subMinutes((int) config('installer.scheduler_heartbeat_minutes')));
        });
    }

    private function usesHttps(): bool
    {
        $frontUrl = (string) ($this->safely(fn () => SiteSetting::current()?->front_url) ?: config('app.front_url'));

        return Str::startsWith((string) config('app.url'), 'https://') && Str::startsWith($frontUrl, 'https://');
    }

    private function diskHasSpace(): bool
    {
        $info = $this->system->info();

        return $info['disk_free'] === null || ! $info['disk_total'] || (int) config('biscuit.disk_free_warning_percent') <= $info['disk_free'] / $info['disk_total'] * 100;
    }

    private function diskDetail(): ?string
    {
        $free = $this->system->info()['disk_free'];

        return $free === null ? null : __('空き :size GB', ['size' => number_format($free / 1024 ** 3, 1)]);
    }

    /**
     * @return array{key: string, label: string, level: 'required', ok: bool, detail: string|null}
     */
    private function required(string $key, string $label, bool $ok, ?string $detail = null): array
    {
        return ['key' => $key, 'label' => $label, 'level' => 'required', 'ok' => $ok, 'detail' => $detail];
    }

    /**
     * @return array{key: string, label: string, level: 'recommended', ok: bool, detail: string|null}
     */
    private function recommended(string $key, string $label, bool $ok, ?string $detail = null): array
    {
        return ['key' => $key, 'label' => $label, 'level' => 'recommended', 'ok' => $ok, 'detail' => $detail];
    }

    private function safely(callable $check): mixed
    {
        try {
            return $check();
        } catch (Throwable) {
            return null;
        }
    }
}
