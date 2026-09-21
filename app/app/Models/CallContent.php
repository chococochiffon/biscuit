<?php

namespace App\Models;

use App\Enums\CallContentPlace;
use App\Enums\CallType;
use Database\Factories\CallContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['call_type', 'call_name', 'content_model_relation_id', 'view_count', 'place'])]
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
            'call_type' => CallType::class,
            'place' => CallContentPlace::class,
        ];
    }

    /**
     * 呼び出し元となるデータ種別の紐付けを取得する。
     */
    public function contentModelRelation(): BelongsTo
    {
        return $this->belongsTo(ContentModelRelation::class);
    }
}
