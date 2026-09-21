<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallContentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'call_type' => $this->call_type->value,
            'view_count' => $this->view_count,
            'model_name' => $this->contentModelRelation->model_name,
            'table_name' => $this->contentModelRelation->table_name,
            'content_type' => $this->contentModelRelation->content_type->value,
        ];
    }
}
