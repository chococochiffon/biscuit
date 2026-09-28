<?php

namespace App\Models;

use App\Enums\CallContentType;
use App\Enums\CallType;
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

    /**
     * table_name が本体のテーブルになっているカスタムページの種類(該当しなければ null)。
     */
    public function customPageType(): ?CustomPageType
    {
        return CustomPageType::findByTableName($this->table_name);
    }

    /**
     * 呼び出し方の組み合わせ(CallType のマトリクス)で使うモデル名。
     * カスタムページの本体のテーブルに紐づく場合は、model_name ではなく種類のベースの型(CallType::CUSTOM_ARTICLE / CUSTOM_SINGLE_PAGE)にする。
     */
    public function matrixModelName(): string
    {
        $type = $this->customPageType();

        if ($type === null) {
            return $this->model_name;
        }

        return $type->hasDetails() ? CallType::CUSTOM_SINGLE_PAGE : CallType::CUSTOM_ARTICLE;
    }
}
