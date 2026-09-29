<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * author は投稿者(id・name・profile_path。profile_path は投稿者ページを公開しているときだけ)で、author_name はその name。
     * published_at は公開開始日時(公開側の並び順 newest() と同じ基準。予約公開した記事は公開された日時になる)。
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
            'author_name' => $this->author()['name'],
            'author' => $this->author(),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'published_at' => $this->publication_start_datetime?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
