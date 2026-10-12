<?php

namespace App\Http\Controllers\API;

use App\Enums\TwoFactorMethod;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TwoFactor\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * chococo のマイページの二段階認証の設定(/api/me/two-factor)。既定はメールの確認コード。
 * setup() で秘密鍵を作ってキャッシュに置き(API はセッションを使わないため。15 分)、QR コードを読み取った認証アプリのコードを
 * update() で確かめられたら認証アプリ(Google Authenticator)に切り替える。destroy() は今のパスワードでメールに戻す。
 */
class MyTwoFactorController extends Controller
{
    /**
     * 設定中の(まだ確かめていない)秘密鍵をキャッシュに置く時間(分)。
     */
    private const SETUP_MINUTES = 15;

    #[OA\Get(
        path: '/me/two-factor',
        summary: 'ログイン中のユーザーの二段階認証の方法',
        security: [['bearer' => []]],
        tags: ['Me'],
        responses: [
            new OA\Response(response: 200, description: 'method(email・totp)・confirmed_at(認証アプリを設定した日時)'),
            new OA\Response(response: 401, description: 'ログインしていない'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->status($request->user())]);
    }

    #[OA\Get(
        path: '/me/two-factor/setup',
        summary: '認証アプリの設定に使う秘密鍵と QR コードを返す(15 分のあいだは同じ秘密鍵を返す)',
        security: [['bearer' => []]],
        tags: ['Me'],
        responses: [
            new OA\Response(response: 200, description: 'secret(設定キー)・qr_code_svg(QR コードの SVG)・otpauth_uri'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 409, description: 'すでに認証アプリを使っている(先にメールに戻す)'),
        ]
    )]
    public function setup(Request $request, Totp $totp): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($user->usesTotp(), 409, __('すでに認証アプリを設定しています。'));

        $secret = Cache::remember($this->setupKey($user), now()->addMinutes(self::SETUP_MINUTES), fn () => $totp->generateSecret());
        $issuer = SiteSetting::current()?->site_title ?? config('app.name');

        return response()->json(['data' => [
            'secret' => $secret,
            'qr_code_svg' => $totp->qrCodeSvg($issuer, $user->email, $secret),
            'otpauth_uri' => $totp->provisioningUri($issuer, $user->email, $secret),
        ]]);
    }

    #[OA\Put(
        path: '/me/two-factor',
        summary: '認証アプリのコードを確かめ、認証アプリでの二段階認証に切り替える',
        security: [['bearer' => []]],
        tags: ['Me'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['code'], properties: [
            new OA\Property(property: 'code', type: 'string', description: '認証アプリに表示された 6 桁のコード'),
        ])),
        responses: [
            new OA\Response(response: 200, description: '切り替えた(GET /me/two-factor と同じ形)'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 422, description: 'コードが違う・設定の期限切れ'),
        ]
    )]
    public function update(Request $request, Totp $totp): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        /** @var User $user */
        $user = $request->user();
        $secret = Cache::get($this->setupKey($user));
        $step = is_string($secret) ? $totp->verify($secret, (string) $request->input('code')) : null;

        if ($step === null) {
            throw ValidationException::withMessages(['code' => __('認証アプリのコードが違います。QR コードを読み取り直して、表示されているコードを入れてください。')]);
        }

        AuditLogger::updateWithLog($user, fn () => $user->enableTotp($secret, $step), ['two_factor' => 'totp_enabled']);
        Cache::forget($this->setupKey($user));

        return response()->json(['data' => $this->status($user)]);
    }

    #[OA\Delete(
        path: '/me/two-factor',
        summary: '認証アプリをやめて、メールの確認コードに戻す(今のパスワードが必要)',
        security: [['bearer' => []]],
        tags: ['Me'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['current_password'], properties: [
            new OA\Property(property: 'current_password', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'メールに戻した(GET /me/two-factor と同じ形)'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 422, description: '今のパスワードが違う'),
        ]
    )]
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:sanctum']]);

        /** @var User $user */
        $user = $request->user();
        AuditLogger::updateWithLog($user, fn () => $user->disableTotp(), ['two_factor' => 'totp_disabled']);

        return response()->json(['data' => $this->status($user)]);
    }

    /**
     * @return array{method: TwoFactorMethod, confirmed_at: string|null}
     */
    private function status(User $user): array
    {
        return [
            'method' => $user->usesTotp() ? TwoFactorMethod::Totp : TwoFactorMethod::Email,
            'confirmed_at' => $user->usesTotp() ? $user->two_factor_confirmed_at?->toIso8601String() : null,
        ];
    }

    private function setupKey(User $user): string
    {
        return 'user_two_factor_setup:'.$user->id;
    }
}
