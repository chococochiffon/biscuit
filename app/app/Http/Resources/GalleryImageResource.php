<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalleryImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * category は画像が属する分類({id, name})で、未分類なら null。
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
        ];
    }
}
