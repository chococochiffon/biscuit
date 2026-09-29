<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * マイページの記事(ログイン中のユーザーが投稿した記事)。公開側の ArticleResource に、編集と承認の状態に使う値を足したもの。
 */
class MyArticleResource extends JsonResource
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
            'title' => $this->title,
            'parent_path' => $this->parent_path,
            'slug' => $this->slug,
            'path' => $this->path,
            'content' => $this->content,
            'thumbnail_url' => $this->thumbnail_url,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'approval' => $this->approval->value,
            'approval_label' => $this->approval->label(),
            'review_comment' => $this->review_comment,
            'publication_start_datetime' => $this->publication_start_datetime?->toIso8601String(),
            'publication_end_datetime' => $this->publication_end_datetime?->toIso8601String(),
            'first_published_at' => $this->first_published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
