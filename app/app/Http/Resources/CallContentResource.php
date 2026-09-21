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
            'id' => $this->id,
            'call_type' => $this->call_type->value,
            'call_name' => $this->call_name,
            'content_model_relation_id' => $this->content_model_relation_id,
            'content_model_relation' => new ContentModelRelationResource($this->whenLoaded('contentModelRelation')),
            'view_count' => $this->view_count,
            'place' => $this->place->value,
        ];
    }
}
