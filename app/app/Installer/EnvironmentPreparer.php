<?php

namespace App\Installer;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * install.sh が最初に呼ぶ(biscuit:install --prepare): .env.example から作った .env に、本番向けの初期値を入れる。
 * - APP_ENV=production・APP_DEBUG=false・LOG_LEVEL=info
 * - APP_KEY(まだなければ生成)・PV の記録で chococo と共有する鍵 PAGE_VIEW_FORWARD_KEY(まだなければ生成)
 * - 管理画面(8080)・公開側(80)のポートと URL の既定値(サイトの段で変えられる)
 * - 配布物の DB のパスワード(.env.example の値)は消す(データベースの段で決めるまで使わせない)
 * すでに値があるもの(APP_KEY・共有の鍵・URL)は上書きしないので、何度呼んでもよい。
 */
class EnvironmentPreparer
{
    public function __construct(private EnvironmentWriter $environment) {}

    public function prepare(): void
    {
        $values = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'LOG_LEVEL' => 'info',
            // ポートは、install.sh が渡した値(コンテナの環境変数)・.env の値・既定値の順
            'BISCUIT_ADMIN_PORT' => (string) (getenv('BISCUIT_ADMIN_PORT') ?: $this->environment->get('BISCUIT_ADMIN_PORT') ?: 8080),
            'BISCUIT_FRONT_PORT' => (string) (getenv('BISCUIT_FRONT_PORT') ?: $this->environment->get('BISCUIT_FRONT_PORT') ?: 80),
            // 待ち受けるアドレス(HTTPS のリバースプロキシを同じサーバーに置くときは 127.0.0.1)。IP アドレスでなければ既定の 0.0.0.0
            'BISCUIT_BIND_ADDRESS' => $this->bindAddress((string) (getenv('BISCUIT_BIND_ADDRESS') ?: $this->environment->get('BISCUIT_BIND_ADDRESS') ?: '')),
        ];

        if (! filled($this->environment->get('PAGE_VIEW_FORWARD_KEY'))) {
            $values['PAGE_VIEW_FORWARD_KEY'] = Str::random(48);
        }

        // まだデータベースの段を終えていなければ、配布物の DB のパスワードを残さない
        if (! app(InstallationState::class)->isCompleted(InstallerStep::Database)) {
            $values['DB_PASSWORD'] = '';
        }

        $adminPort = $values['BISCUIT_ADMIN_PORT'];
        $frontPort = $values['BISCUIT_FRONT_PORT'];

        if (! app(InstallationState::class)->isCompleted(InstallerStep::Site)) {
            $values['APP_URL'] = 'http://localhost'.($adminPort === '80' ? '' : ":{$adminPort}");
            $values['FRONT_URL'] = 'http://localhost'.($frontPort === '80' ? '' : ":{$frontPort}");
        }

        $this->environment->set($values);

        if ((string) config('app.key') === '') {
            Artisan::call('key:generate', ['--force' => true]);
        }

        InstallerLog::info('.env に本番向けの初期値を入れました。', ['admin_port' => $adminPort, 'front_port' => $frontPort]);
    }

    private function bindAddress(string $address): string
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? $address : '0.0.0.0';
    }
}
