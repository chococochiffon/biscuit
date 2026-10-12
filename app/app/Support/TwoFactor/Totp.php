<?php

namespace App\Support\TwoFactor;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

/**
 * Google Authenticator などの認証アプリ(TOTP。30 秒ごとに変わる 6 桁のコード)の秘密鍵の生成・QR コード・コードの確認。
 * 管理者・ユーザーで共通に使う(Models\Concerns\HasTwoFactor)。
 */
class Totp
{
    /**
     * 前後に許すコードの数(30 秒ずつ。端末の時計のずれを 30 秒まで許す)。
     */
    private const WINDOW = 1;

    public function __construct(private Google2FA $google2fa) {}

    /**
     * 新しい秘密鍵(Base32 の 32 文字)を作る。
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * 認証アプリに登録する URL(otpauth://)。$issuer はアプリに出す発行元(サイト名など)、$account はアカウント(メールアドレス)。
     */
    public function provisioningUri(string $issuer, string $account, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl($issuer, $account, $secret);
    }

    /**
     * 認証アプリで読み取る QR コード(SVG の文字列。HTML に埋め込めるよう、先頭の XML 宣言は外す)。
     */
    public function qrCodeSvg(string $issuer, string $account, string $secret, int $size = 192): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return trim((string) preg_replace('/\A<\?xml[^>]*\?>/', '', $writer->writeString($this->provisioningUri($issuer, $account, $secret))));
    }

    /**
     * コードを確かめ、正しければそのコードの時刻(30 秒ごとの番号)を返す。間違い・$lastUsedStep 以前のコード(使用済み)なら null。
     */
    public function verify(string $secret, string $code, ?int $lastUsedStep = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/\A\d{6}\z/', $code)) {
            return null;
        }

        try {
            $step = $this->google2fa->verifyKeyNewer($secret, $code, $lastUsedStep ?? 0, self::WINDOW);
        } catch (Throwable) {
            return null;
        }

        return is_int($step) ? $step : null;
    }
}
