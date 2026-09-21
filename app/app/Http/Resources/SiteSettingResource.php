<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SiteSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'site_title' => $this->site_title,
            'description' => $this->description,
            'site_icon_url' => $this->site_icon ? Storage::disk('public')->url($this->site_icon) : null,
            'site_image_url' => $this->site_image ? Storage::disk('public')->url($this->site_image) : null,
        ];
    }
}
