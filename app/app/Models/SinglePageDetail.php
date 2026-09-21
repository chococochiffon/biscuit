<?php

namespace App\Models;

use Database\Factories\SinglePageDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['single_page_id', 'sub_title', 'contents'])]
class SinglePageDetail extends Model
{
    /** @use HasFactory<SinglePageDetailFactory> */
    use HasFactory, SoftDeletes;

    /**
     * 詳細が紐づく固定ページを取得する。
     */
    public function singlePage(): BelongsTo
    {
        return $this->belongsTo(SinglePage::class);
    }
}
