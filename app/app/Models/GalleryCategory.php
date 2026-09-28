<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use Database\Factories\GalleryCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'sort_order'])]
class GalleryCategory extends Model
{
    /** @use HasFactory<GalleryCategoryFactory> */
    use HasFactory, HasSortOrder, SoftDeletes;

    /**
     * この分類に属するギャラリー画像を取得する。
     */
    public function images(): HasMany
    {
        return $this->hasMany(GalleryImage::class);
    }
}
