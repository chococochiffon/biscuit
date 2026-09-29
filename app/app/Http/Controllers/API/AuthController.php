<?php

namespace App\Http\Controllers\API;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\LoginRequest;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * chococo のマイページのログイン・ログアウト。ログインすると API トークン(Laravel Sanctum)を発行する。
 * chococo は別ドメインのため Cookie のセッションは使わず、chococo のサーバーがトークンを持って /api/me/* を呼ぶ。
 * ログインできるのは管理画面で登録したユーザーだけ(登録の API は持たない)。
 */
class AuthController extends Controller
{
    /**
     * 発行する API トークンの名前。
     */
    public const TOKEN_NAME = 'chococo';

    #[OA\Post(
        path: '/auth/login',
        summary: 'ユーザーとしてログインし、API トークンを発行する',
        tags: ['Me'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['email', 'password'], properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email'),
            new OA\Property(property: 'password', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'token(API トークン。Authorization: Bearer で送る)・expires_at(有効期限)・user(ログインしたユーザー。GET /me と同じ形)'),
            new OA\Response(response: 422, description: 'メールアドレスかパスワードが違う'),
            new OA\Response(response: 429, description: 'ログインの試行回数が多すぎる'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            AuditLogger::record(AuditAction::LoginFailed, 'user', metadata: ['email' => $request->validated('email')]);

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        $token = DB::transaction(function () use ($user) {
            $token = $user->createToken(self::TOKEN_NAME, expiresAt: now()->addMinutes((int) config('sanctum.expiration')));
            AuditLogger::record(AuditAction::Login, $user, actor: $user);

            return $token;
        });

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
