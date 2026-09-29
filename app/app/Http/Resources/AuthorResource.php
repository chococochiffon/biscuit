<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 公開側の投稿者ページのプロフィール。名前は名前の表示設定に従い、アカウント名・メールアドレス・誕生日は出さない。
 *
 * @mixin User
 */
class AuthorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->authorName(),
            'profile_path' => $this->authorProfilePath(),
            'user_image_url' => $this->detail->user_image_url,
            'comment' => $this->detail->comment,
            'skills' => UserSkillResource::collection($this->detail->skills),
        ];
    }
}
