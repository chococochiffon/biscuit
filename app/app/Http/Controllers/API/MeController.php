<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Concerns\SavesUserProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\UpdateMyPasswordRequest;
use App\Http\Requests\API\UpdateMyProfileImageRequest;
use App\Http\Requests\API\UpdateMyProfileRequest;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * chococo のマイページ: ログイン中のユーザーの取得と、プロフィール・アイコン画像・パスワードの変更。
 * プロフィールの保存は管理画面のユーザー編集と共通(SavesUserProfile)で、操作は監査ログに操作者 user として残す。
 */
class MeController extends Controller
{
    use SavesUserProfile;

    #[OA\Get(
        path: '/me',
        summary: 'ログイン中のユーザー(ユーザー詳細とスキルを含む)を取得する',
        security: [['bearer' => []]],
        tags: ['Me'],
        responses: [
            new OA\Response(response: 200, description: 'id・name・email と detail(first_name・family_name・nick_name・birthday・comment・view_flag・name_settings・user_image_url・skills。未登録なら null)'),
            new OA\Response(response: 401, description: 'ログインしていない(トークンがない・無効・期限切れ)'),
        ]
    )]
    public function show(Request $request): MeResource
    {
        return new MeResource($request->user()->load('detail.skills'));
    }

    #[OA\Put(
        path: '/me/profile',
        summary: 'プロフィール(名前・メールアドレス・ユーザー詳細・スキル)を更新する',
        security: [['bearer' => []]],
        tags: ['Me'],
        responses: [
            new OA\Response(response: 200, description: '更新後のユーザー(GET /me と同じ形)'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 422, description: '入力値が不正(管理画面のユーザー編集と同じルール)'),
        ]
    )]
    public function updateProfile(UpdateMyProfileRequest $request): MeResource
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            $before = AuditLogger::snapshot($user, $this->auditDetail($user->detail));

            $user->update([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
            ]);

            $detail = $user->detail()->updateOrCreate([], $this->userDetailAttributes($request));
            $skills = $this->syncSkills($detail, $request->validated('user_detail.skills', []));

            AuditLogger::updated($user, $before, ['skills' => $skills->summary()], extra: $this->auditDetail($detail->fresh()));
        });

        return new MeResource($user->fresh()->load('detail.skills'));
    }

    #[OA\Post(
        path: '/me/profile/image',
        summary: 'アイコン画像を変更する(multipart/form-data。切り抜き範囲 crop[x]・crop[y]・crop[width]・crop[height] は任意)',
        security: [['bearer' => []]],
        tags: ['Me'],
        responses: [
            new OA\Response(response: 200, description: '更新後のユーザー(GET /me と同じ形)'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 422, description: '画像が不正、またはプロフィールが未登録'),
        ]
    )]
    public function updateImage(UpdateMyProfileImageRequest $request): MeResource
    {
        /** @var User $user */
        $user = $request->user();
        $detail = $user->detail ?? throw ValidationException::withMessages(['image' => __('先にプロフィールを登録してください。')]);

        DB::transaction(function () use ($request, $user, $detail) {
            $before = AuditLogger::snapshot($user, $this->auditDetail($detail));

            $detail->update(['user_image' => $detail->storeUserImage($request->file('image'), $request->crop())]);

            AuditLogger::updated($user, $before, extra: $this->auditDetail($detail->fresh()));
        });

        return new MeResource($user->fresh()->load('detail.skills'));
    }

    #[OA\Put(
        path: '/me/password',
        summary: 'パスワードを変更する(今のパスワードが必要。ほかの端末のログインは無効になる)',
        security: [['bearer' => []]],
        tags: ['Me'],
        responses: [
            new OA\Response(response: 204, description: '変更した'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 422, description: '今のパスワードが違う、または新しいパスワードが不正'),
        ]
    )]
    public function updatePassword(UpdateMyPasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $user) {
            $before = AuditLogger::snapshot($user);

            $user->update(['password' => $request->validated('password')]);
            // 使っているトークン以外(ほかの端末のログイン)は無効にする
            $user->expireTokens(except: $user->currentAccessToken()->getKey());

            // パスワードは値を残さず、変更したことだけを残す
            AuditLogger::updated($user, $before, ['password_changed' => true]);
        });

        return response()->json(status: 204);
    }
}
