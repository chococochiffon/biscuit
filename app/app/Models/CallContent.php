<?php

namespace App\Models;

use App\Enums\CallContentType;
use Database\Factories\CallContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['content_type', 'model_name', 'view_count', 'place'])]
class CallContent extends Model
{
    /** @use HasFactory<CallContentFactory> */
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
