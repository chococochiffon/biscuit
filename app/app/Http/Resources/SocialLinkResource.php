<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SocialLinkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * service はフロントエンドがアイコンを出し分けるための識別子(例: youtube)。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service' => $this->service->apiName(),
            'name' => $this->name,
            'url' => $this->url,
        ];
    }
}
