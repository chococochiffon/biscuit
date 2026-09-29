<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ログイン中のユーザー(chococo のマイページ用)。プロフィールの編集に使うため、公開側の UserDetailResource と違い
 * 誕生日・公開するか(view_flag)などの入力値をそのまま返す。ユーザー詳細が未登録なら detail は null。
 */
class MeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detail = $this->detail;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'detail' => $detail === null ? null : [
                'first_name' => $detail->first_name,
                'family_name' => $detail->family_name,
                'nick_name' => $detail->nick_name,
                'birthday' => $detail->birthday?->format('Y-m-d'),
                'comment' => $detail->comment,
                'view_flag' => $detail->view_flag,
                'name_settings' => $detail->name_settings->value,
                'user_image_url' => $detail->user_image_url,
                'skills' => UserSkillResource::collection($detail->skills),
            ],
        ];
    }
}
