<?php

namespace App\Http\Controllers\API;

use App\Enums\AuditAction;
use App\Enums\TwoFactorMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\LoginRequest;
use App\Http\Resources\MeResource;
use App\Models\LoginCode;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TwoFactor\TotpChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * chococo のマイページのログイン・ログアウト。ログインは二段階で、メールアドレスとパスワードが正しければ確認コードをメールで送って
 * チャレンジを返し(login)、チャレンジとコードを確かめると API トークン(Laravel Sanctum)を発行する(verifyCode)。
 * chococo は別ドメインのため Cookie のセッションは使わず、chococo のサーバーがトークンを持って /api/me/* を呼ぶ。
 * ログインできるのは管理画面で登録したユーザーだけ(登録の API は持たない)。
 * 認証アプリ(Google Authenticator)を設定したユーザーにはメールを送らず、認証アプリのコードを待つチャレンジ(TotpChallenge)を返す(method: totp)。
 * 機種変更などで認証アプリを使えないときは、sendEmailCode() でメールの確認コードに切り替えられる。
 */
class AuthController extends Controller
{
    /**
     * 発行する API トークンの名前。
     */
    public const TOKEN_NAME = 'chococo';

    #[OA\Post(
        path: '/auth/login',
        summary: 'メールアドレスとパスワードを確かめ、ログインの確認コードをメールで送る(二段階認証の 1 段目)',
        tags: ['Me'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['email', 'password'], properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email'),
            new OA\Property(property: 'password', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'two_factor(true)・method(email: メールの確認コード、totp: 認証アプリのコード)・challenge(コードの入力で送るチャレンジ)'),
            new OA\Response(response: 422, description: 'メールアドレスかパスワードが違う'),
            new OA\Response(response: 429, description: 'ログインの試行回数が多すぎる'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        // 招待されてまだプロフィールとパスワードを登録していない(無効な)ユーザーも、登録がないときと同じ応答にする
        if ($user === null || ! $user->active_flag || ! Hash::check($request->validated('password'), $user->password)) {
            AuditLogger::record(AuditAction::LoginFailed, 'user', metadata: ['email' => $request->validated('email')]);

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        if ($user->usesTotp()) {
            return response()->json(['two_factor' => true, 'method' => TwoFactorMethod::Totp, 'challenge' => TotpChallenge::issue($user)]);
        }

        return response()->json(['two_factor' => true, 'method' => TwoFactorMethod::Email, 'challenge' => $this->issueLoginCode($user)]);
    }

    /**
     * メールで確認コードを送り、チャレンジを返す。
     *
     * @param  array<string, mixed>  $metadata
     */
    private function issueLoginCode(User $user, array $metadata = []): string
    {
        return DB::transaction(function () use ($user, $metadata) {
            $challenge = LoginCode::issue($user);
            AuditLogger::record(AuditAction::LoginCodeSent, $user, metadata: $metadata, actor: $user);

            return $challenge;
        });
    }

    #[OA\Post(
        path: '/auth/login/verify',
        summary: 'ログインの確認コード(認証アプリを設定したユーザーは認証アプリのコード)を確かめ、API トークンを発行する(二段階認証の 2 段目)',
        tags: ['Me'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['challenge', 'code'], properties: [
            new OA\Property(property: 'challenge', type: 'string'),
            new OA\Property(property: 'code', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'token(API トークン。Authorization: Bearer で送る)・expires_at(有効期限)・user(ログインしたユーザー。GET /me と同じ形)'),
            new OA\Response(response: 422, description: '確認コードが違う・有効期限切れ・間違えすぎ(認証アプリのコードを間違えすぎたら challenge のエラーで、ログインからやり直す)'),
            new OA\Response(response: 429, description: '試行回数が多すぎる'),
        ]
    )]
    public function verifyCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        if (TotpChallenge::user($validated['challenge']) !== null) {
            return $this->verifyTotp($validated['challenge'], $validated['code']);
        }

        $user = LoginCode::verify(User::class, $validated['challenge'], $validated['code']);

        if (! $user instanceof User || ! $user->active_flag) {
            AuditLogger::record(AuditAction::LoginFailed, 'user', metadata: ['reason' => 'login_code']);

            throw ValidationException::withMessages(['code' => __('確認コードが違うか、有効期限が切れています。')]);
        }

        return $this->loginResponse($user);
    }

    /**
     * 認証アプリのコードを確かめてログインする。間違えすぎたら、チャレンジを消してログインからやり直してもらう。
     */
    private function verifyTotp(string $challenge, string $code): JsonResponse
    {
        $user = TotpChallenge::user($challenge);

        if ($user !== null && $user->active_flag && $user->verifyTotp($code)) {
            TotpChallenge::forget($challenge);

            return $this->loginResponse($user);
        }

        AuditLogger::record(AuditAction::LoginFailed, 'user', metadata: ['email' => $user?->email, 'reason' => 'totp']);

        if (! TotpChallenge::failed($challenge)) {
            throw ValidationException::withMessages(['challenge' => __('認証アプリのコードを続けて間違えたため、もう一度ログインしてください。')]);
        }

        throw ValidationException::withMessages(['code' => __('認証アプリのコードが違います。')]);
    }

    private function loginResponse(User $user): JsonResponse
    {
        return DB::transaction(function () use ($user) {
            AuditLogger::record(AuditAction::Login, $user, actor: $user);

            return self::tokenResponse($user);
        });
    }

    #[OA\Post(
        path: '/auth/login/email-code',
        summary: '認証アプリのコードの代わりに、メールで確認コードを送って新しいチャレンジを返す(機種変更などで認証アプリを使えないとき)',
        tags: ['Me'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['challenge'], properties: [
            new OA\Property(property: 'challenge', type: 'string', description: 'ログイン(method: totp)で返したチャレンジ'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'method(email)・challenge(メールの確認コードのチャレンジ)'),
            new OA\Response(response: 422, description: 'チャレンジが使えない(ログインからやり直す)'),
            new OA\Response(response: 429, description: '試行回数が多すぎる'),
        ]
    )]
    public function sendEmailCode(Request $request): JsonResponse
    {
        $validated = $request->validate(['challenge' => ['required', 'string']]);
        $user = TotpChallenge::user($validated['challenge']);

        if ($user === null || ! $user->active_flag) {
            throw ValidationException::withMessages(['challenge' => __('もう一度ログインからやり直してください。')]);
        }

        TotpChallenge::forget($validated['challenge']);

        return response()->json(['method' => TwoFactorMethod::Email, 'challenge' => $this->issueLoginCode($user, ['instead_of_totp' => true])]);
    }

    #[OA\Post(
        path: '/auth/login/resend',
        summary: 'ログインの確認コードを送り直し、新しいチャレンジを返す(それまでのコードは使えなくなる)',
        tags: ['Me'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['challenge'], properties: [
            new OA\Property(property: 'challenge', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'challenge(新しいチャレンジ)'),
            new OA\Response(response: 422, description: 'チャレンジが使えない(ログインからやり直す)'),
            new OA\Response(response: 429, description: '試行回数が多すぎる'),
        ]
    )]
    public function resendCode(Request $request): JsonResponse
    {
        $validated = $request->validate(['challenge' => ['required', 'string']]);

        $challenge = LoginCode::resend(User::class, $validated['challenge'])
            ?? throw ValidationException::withMessages(['challenge' => __('もう一度ログインからやり直してください。')]);

        AuditLogger::record(AuditAction::LoginCodeSent, 'user', metadata: ['resent' => true]);

        return response()->json(['challenge' => $challenge]);
    }

    /**
     * API トークンを発行し、ログインの応答(token・expires_at・user)を返す。招待の受諾(InvitationController)でも使う。
     */
    public static function tokenResponse(User $user): JsonResponse
    {
        $token = $user->createToken(self::TOKEN_NAME, expiresAt: now()->addMinutes((int) config('sanctum.expiration')));

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new MeResource($user->load('detail.skills')),
        ]);
    }

    #[OA\Post(
        path: '/auth/logout',
        summary: 'ログアウトし、使っている API トークンを無効にする',
        security: [['bearer' => []]],
        tags: ['Me'],
        responses: [
            new OA\Response(response: 204, description: 'ログアウトした'),
            new OA\Response(response: 401, description: 'ログインしていない'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            // トークンは削除せず、有効期限を切らして無効にする(物理削除しない方針のため)
            $request->user()->currentAccessToken()->forceFill(['expires_at' => now()])->save();
            AuditLogger::record(AuditAction::Logout, $request->user());
        });

        return response()->json(status: 204);
    }
}
