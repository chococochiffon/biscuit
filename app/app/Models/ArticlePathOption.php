<?php

namespace App\Models;

use App\Models\Concerns\HasSortOrder;
use Database\Factories\ArticlePathOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ユーザー(chococo のマイページ)が記事を投稿するときに選ぶ投稿先(親パス)。管理者が登録する。
 * 記事には選んだ投稿先の parent_path の文字列を保存するため、投稿先を変更・削除しても既存の記事の URL は変わらない。
 */
#[Fillable(['label', 'parent_path', 'sort_order'])]
class ArticlePathOption extends Model
{
    /** @use HasFactory<ArticlePathOptionFactory> */
    use HasFactory, HasSortOrder, SoftDeletes;
}
