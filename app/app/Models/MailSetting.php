<?php

namespace App\Models;

use App\Support\MailTransport;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Throwable;

/**
 * システムが使うメールの設定(管理画面の「メールの設定」。スーパー管理者だけが変えられる)。
 * 1 件だけ登録する前提で、登録があればメールを送るとき(メールのマネージャーを作るとき。AppServiceProvider)に
 * .env の MAIL_* より優先して config に入れる(applyToConfig())。未登録なら .env の設定のまま。
 * SMTP のパスワード・Resend の API キーは暗号化して保存し、画面・監査ログには出さない。
 */
#[Fillable(['driver', 'host', 'port', 'encryption', 'username', 'password', 'api_key', 'from_address', 'from_name'])]
#[Hidden(['password', 'api_key'])]
class MailSetting extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
            'api_key' => 'encrypted',
        ];
    }

    /**
     * 登録されているメールの設定(未登録なら null)。
     */
    public static function current(): ?self
    {
        return self::query()->latest('id')->first();
    }

    /**
     * 登録されているメールの設定を config に入れる(未登録・テーブルがまだない(インストールの途中)ときは何もしない)。
     */
    public static function applyToConfig(): void
    {
        try {
            $setting = self::current();
        } catch (Throwable) {
            return;
        }

        if ($setting === null) {
            return;
        }

        config([
            'mail.default' => $setting->driver,
            'mail.mailers.'.$setting->driver => [...(array) config('mail.mailers.'.$setting->driver), ...MailTransport::mailerConfig($setting->transportValues())],
            'mail.from.address' => $setting->from_address,
            'mail.from.name' => $setting->from_name ?? config('mail.from.name'),
        ]);

        if ($setting->driver === 'resend') {
            config(['services.resend.key' => $setting->api_key]);
        }
    }

    /**
     * 送り方の設定(MailTransport::mailerConfig() に渡す値)。
     *
     * @return array{driver: string, host: string|null, port: int|null, encryption: string|null, username: string|null, password: string|null, api_key: string|null}
     */
    public function transportValues(): array
    {
        return [
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'password' => $this->password,
            'api_key' => $this->api_key,
        ];
    }
}
