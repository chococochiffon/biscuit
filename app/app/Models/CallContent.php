<?php

namespace App\Models;

use App\Enums\CallContentPlace;
use App\Enums\CallType;
use Database\Factories\CallContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['call_type', 'call_name', 'title', 'subtitle', 'content_model_relation_id', 'view_count', 'place', 'sort_order'])]
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
     * 指定した設置場所の呼び出しコンテンツを、並び順(sort_order、同順ならid)で取得する。
     */
    #[Scope]
    protected function forPlace(Builder $query, CallContentPlace $place): void
    {
        $query->where('place', $place)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * 呼び出し元となるデータ種別の紐付けを取得する。
     */
    public function contentModelRelation(): BelongsTo
    {
        return $this->belongsTo(ContentModelRelation::class);
    }
}
