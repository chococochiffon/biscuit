<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserDetailResource extends JsonResource
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
            'first_name' => $this->first_name,
            'family_name' => $this->family_name,
            'nick_name' => $this->nick_name,
            'user_image_url' => $this->user_image ? Storage::disk('public')->url($this->user_image) : null,
            'comment' => $this->comment,
            'name_settings' => $this->name_settings->value,
        ];
    }
}
