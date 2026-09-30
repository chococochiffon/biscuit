<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ログイン中のユーザーが投稿したギャラリーの画像(chococo のマイページ用)。公開側の GalleryImageResource に、
 * 公開ステータスと差し戻しの理由を足したもの。
 */
class MyGalleryImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * approval は draft(下書き)・pending(承認待ち)・published(公開)。review_comment は管理者が下書きに戻したときの理由。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'comment' => $this->comment,
            'image_url' => $this->image_url,
            'category' => $this->category ? new GalleryCategoryResource($this->category) : null,
            'approval' => $this->approval->value,
            'approval_label' => $this->approval->label(),
            'review_comment' => $this->review_comment,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
