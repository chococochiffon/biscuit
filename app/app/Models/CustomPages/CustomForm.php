<?php

namespace App\Models\CustomPages;

use App\Enums\CustomFormType;
use App\Models\Concerns\BelongsToCustomPageType;
use App\Models\Concerns\HasSortOrder;
use App\Models\CustomPageType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * カスタムフォームの項目定義(customs_○○_forms の 1 行)。
 * customs_form_options はプルダウン・ラジオ・チェックボックスの選択肢(文字列の配列。JSON で保存)。
 */
#[Fillable(['parts_name', 'customs_form_type', 'customs_form_options', 'sort_order'])]
class CustomForm extends Model
{
    use BelongsToCustomPageType, HasSortOrder, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customs_form_type' => CustomFormType::class,
            'customs_form_options' => 'array',
        ];
    }

    protected static function tableNameFor(CustomPageType $type): string
    {
        return $type->formsTableName();
    }

    /**
     * 選択肢(選択肢を持たない入力形式なら空の配列)。
     *
     * @return list<string>
     */
    public function options(): array
    {
        return $this->customs_form_type->hasOptions() ? array_values($this->customs_form_options ?? []) : [];
    }
}
