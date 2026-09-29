<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tag_name'])]
#[Hidden(['unique_tag_name'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, SoftDeletes;

    /**
     * 名前のインクリメンタル検索の候補(名前の部分一致、名前順で 10 件まで。キーワードが空なら先頭から)。
     * 管理画面の記事フォームと、chococo のマイページの記事の編集で共通の条件。
     */
    #[Scope]
    protected function suggest(Builder $query, string $keyword): void
    {
        $query->when($keyword !== '', fn ($query) => $query->where('tag_name', 'like', '%'.$keyword.'%'))
            ->orderBy('tag_name')
            ->limit(10);
    }

    /**
     * タグが付与されている記事を取得する。
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }
}
