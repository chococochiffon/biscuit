<?php

namespace App\Http\Controllers\API;

use App\Enums\AuditAction;
use App\Http\Controllers\Concerns\SavesUserProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\AcceptInvitationRequest;
use App\Models\UserInvitation;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * 管理者からの招待の受諾(ログイン前)。招待のメールのリンク(公開側 chococo の /invitation?token=…&email=…)で、
 * 招待されたユーザーがアカウント名・パスワード・プロフィールを登録すると、ユーザーを有効にしてそのままログインさせる。
 * リンクは有効期限(config('auth.invitations.expire'))を過ぎる・受諾する・管理者が再送すると使えなくなる。
 */
class InvitationController extends Controller
{
    use SavesUserProfile;

    #[OA\Get(
        path: '/auth/invitation',
        summary: '招待のリンクがまだ使えるかを確かめ、登録フォームの初期値(アカウント名・メールアドレス)を返す',
        tags: ['Auth'],
        parameters: [
            new OA\Parameter(name: 'token', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'email', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'data.name(アカウント名の初期値)・data.email'),
            new OA\Response(response: 404, description: 'リンクが無効・期限切れ・受諾済み'),
            new OA\Response(response: 429, description: '回数が多すぎる'),
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $invitation = UserInvitation::findUsable((string) $request->query('email'), (string) $request->query('token'));

        abort_if($invitation === null, 404, self::invalidLinkMessage());

        return response()->json(['data' => [
            'name' => $invitation->user->name,
            'email' => $invitation->user->email,
        ]]);
    }

    #[OA\Post(
        path: '/auth/invitation',
        summary: '招待を受けてアカウント名・パスワード・プロフィールを登録し、ログインする(API トークンを発行する)',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'token(API トークン)・expires_at・user(ログイン中のユーザー)'),
            new OA\Response(response: 422, description: '入力値が不正、またはリンクが無効・期限切れ(token のエラー)'),
            new OA\Response(response: 429, description: '回数が多すぎる'),
        ]
    )]
    public function accept(AcceptInvitationRequest $request): JsonResponse
    {
        $invitation = $request->invitation() ?? throw ValidationException::withMessages(['token' => self::invalidLinkMessage()]);
        $user = $invitation->user;

        return DB::transaction(function () use ($request, $invitation, $user) {
            $before = AuditLogger::snapshot($user);

            $user->update([
                'name' => $request->validated('name'),
                'password' => $request->validated('password'),
                'active_flag' => true,
            ]);
            $detail = $user->detail()->updateOrCreate([], $this->userDetailAttributes($request));
            $skills = $this->syncSkills($detail, $request->validated('user_detail.skills', []));
            $invitation->update(['accepted_at' => now()]);

            // 操作者は招待を受けたユーザー(まだログインしていない)。パスワードは値を残さない(snapshot は hidden の password を除く)
            AuditLogger::record(
                AuditAction::InvitationAccepted,
                $user,
                changes: AuditLogger::diff($before, AuditLogger::snapshot($user, $this->auditDetail($detail->fresh()))),
                metadata: ['skills' => $skills->summary()],
                actor: $user,
            );

            return AuthController::tokenResponse($user);
        });
    }

    private static function invalidLinkMessage(): string
    {
        return __('招待のリンクが無効か、有効期限が切れています。管理者に招待の再送を依頼してください。');
    }
}
