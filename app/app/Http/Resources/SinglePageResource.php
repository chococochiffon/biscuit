<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SinglePageResource extends JsonResource
{
    /**
     * builder を含めるか(パス解決 API・プレビュー API だけが withBuilder() で含める)。
     */
    private bool $includesBuilder = false;

    /**
     * 公開側に出すページビルダーの内容(BuilderPresenter::forPublic() の結果。使わない・未公開なら null)。
     *
     * @var array<string, mixed>|null
     */
    private ?array $builder = null;

    /**
     * ページビルダーの内容を builder として含める。
     *
     * @param  array<string, mixed>|null  $builder
     */
    public function withBuilder(?array $builder): static
    {
        $this->includesBuilder = true;
        $this->builder = $builder;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'short_sentences' => $this->short_sentences,
            'header_image_url' => $this->header_image_url,
            'parent_path' => $this->parent_path,
            'slug' => $this->slug,
            'path' => $this->path,
            'details' => SinglePageDetailResource::collection($this->whenLoaded('details')),
            'builder' => $this->when($this->includesBuilder, fn () => $this->builder),
        ];
    }
}
