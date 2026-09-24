<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SinglePageResource extends JsonResource
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
            'title' => $this->title,
            'short_sentences' => $this->short_sentences,
            'header_image_url' => $this->header_image ? Storage::disk('public')->url($this->header_image) : null,
            'parent_path' => $this->parent_path,
            'slug' => $this->slug,
            'path' => $this->path,
            'details' => SinglePageDetailResource::collection($this->whenLoaded('details')),
        ];
    }
}
