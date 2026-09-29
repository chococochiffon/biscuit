<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use App\Models\User;
use OpenApi\Attributes as OA;

/**
 * 公開側(chococo)の投稿者ページ(/authors/{id})のプロフィール。投稿者の記事は記事一覧 API(GET /api/articles?author={id})で取得する。
 */
class AuthorController extends Controller
{
    #[OA\Get(
        path: '/authors/{id}',
        summary: '投稿者のプロフィール(表示名・アイコン画像・コメント・スキル)を取得する',
        tags: ['Authors'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ユーザーの id', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '投稿者(id・name・profile_path・user_image_url・comment・skills)'),
            new OA\Response(response: 404, description: '投稿者ページを公開していない(プロフィールを公開していない・論理削除した・ユーザー詳細が未登録)'),
        ]
    )]
    public function show(int $id): AuthorResource
    {
        $user = User::query()->with('detail.skills')->findOrFail($id);

        abort_unless($user->hasPublicProfile(), 404);

        return new AuthorResource($user);
    }
}
