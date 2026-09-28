<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomPageTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * base_type は article / single_page、path は公開側の一覧の URL(例: /recipes)。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'base_type' => $this->base_type->apiName(),
            'path' => $this->publicPath(),
        ];
    }
}
