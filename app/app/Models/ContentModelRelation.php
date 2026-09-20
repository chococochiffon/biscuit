<?php

namespace App\Models;

use App\Enums\CallContentType;
use Database\Factories\ContentModelRelationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['content_type', 'model_name', 'table_name'])]
#[Hidden(['unique_content_type_model_name'])]
class ContentModelRelation extends Model
{
    /** @use HasFactory<ContentModelRelationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_type' => CallContentType::class,
        ];
    }
}
