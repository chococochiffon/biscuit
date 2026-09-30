<?php

namespace App\Http\Controllers\API;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\ForgotPasswordRequest;
use App\Http\Requests\API\ResetPasswordRequest;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * chococo のマイページのパスワード再設定(ログイン前)。Laravel のパスワードブローカー(password_reset_tokens)を使う。
 * 再設定のトークンは使い捨てで、ブローカーが使ったとき・発行し直したときに物理削除する(論理削除の方針の例外)。
 */
class PasswordResetController extends Controller
{
    #[OA\Post(
        path: '/auth/forgot-password',
        summary: 'パスワード再設定のメールを送る(登録がないメールアドレスでも同じ応答を返す)',
        tags: ['Me'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['email'], properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'message(登録があればメールを送った旨)'),
            new OA\Response(response: 422, description: 'メールアドレスの書式が不正'),
            new OA\Response(response: 429, description: '依頼の回数が多すぎる'),
        ]
    )]
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        // 登録の有無を知られないよう、送れなかったとき(登録がない・直前に送ったばかり)も同じ応答にする。
        // 招待されてまだ登録していない(無効な)ユーザーには送らない(招待のリンクから登録してもらう)
        if (User::query()->where('email', $request->validated('email'))->where('active_flag', true)->exists()) {
            Password::broker('users')->sendResetLink(['email' => $request->validated('email')]);
        }

        AuditLogger::record(AuditAction::PasswordResetRequested, 'user', metadata: ['email' => $request->validated('email')]);

        return response()->json([
            'message' => __('ご登録のメールアドレスであれば、パスワード再設定のメールを送りました。メールのリンクから新しいパスワードを設定してください。'),
        ]);
    }

    #[OA\Post(
        path: '/auth/reset-password',
        summary: 'メールのリンクのトークンでパスワードを再設定する(発行済みのログインはすべて無効になる)',
        tags: ['Me'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['token', 'email', 'password', 'password_confirmation'], properties: [
            new OA\Property(property: 'token', type: 'string'),
            new OA\Property(property: 'email', type: 'string', format: 'email'),
            new OA\Property(property: 'password', type: 'string'),
            new OA\Property(property: 'password_confirmation', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 204, description: '再設定した'),
            new OA\Response(response: 422, description: 'リンクが無効・期限切れ、または新しいパスワードが不正'),
            new OA\Response(response: 429, description: '回数が多すぎる'),
        ]
    )]
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                DB::transaction(function () use ($user, $password) {
                    $user->forceFill(['password' => $password])->save();
                    // 再設定の前にログインしていた端末(漏れたかもしれないトークン)はすべて無効にする
                    $user->expireTokens();

                    // パスワードは値を残さず、再設定したことだけを残す
                    AuditLogger::record(AuditAction::PasswordReset, $user, actor: $user);
                });
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(status: 204);
    }
}
