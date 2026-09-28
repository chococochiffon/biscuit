<?php

namespace App\Models;

use App\Enums\CustomPageBaseType;
use App\Models\Concerns\HasSortOrder;
use Database\Factories\CustomPageTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * カスタムページの種類。登録すると、種類ごとのテーブルを CustomPageSchema が作る(カスタム名 recipe の例)。
 * - 本体: user_make_recipes(カスタム名の複数形)
 * - 詳細: user_make_recipe_details(固定ページ型のみ)
 * - カスタムフォームの項目定義: customs_recipe_forms
 * - カスタムフォームの入力値: customs_recipe_form_values
 */
#[Fillable(['name', 'label', 'base_type', 'sort_order'])]
class CustomPageType extends Model
{
    /** @use HasFactory<CustomPageTypeFactory> */
    use HasFactory, HasSortOrder, SoftDeletes;

    /**
     * 種類ごとのテーブルの接頭辞。
     */
    public const TABLE_PREFIX = 'user_make_';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_type' => CustomPageBaseType::class,
        ];
    }

    /**
     * 入力されたカスタム名を、テーブル名の元にする単数形の snake_case にそろえる(例: Recipes → recipe)。
     */
    public static function normalizeName(string $name): string
    {
        return Str::singular(Str::snake(trim($name)));
    }

    /**
     * 固定ページ型(詳細テーブルを持つ)かどうか。
     */
    public function hasDetails(): bool
    {
        return $this->base_type === CustomPageBaseType::SinglePage;
    }

    /**
     * 本体のテーブル名(例: user_make_recipes)。
     */
    public function tableName(): string
    {
        return self::TABLE_PREFIX.Str::plural($this->name);
    }

    /**
     * 詳細のテーブル名(例: user_make_recipe_details)。固定ページ型だけが持つ。
     */
    public function detailsTableName(): string
    {
        return self::TABLE_PREFIX.$this->name.'_details';
    }

    /**
     * カスタムフォームの項目定義のテーブル名(例: customs_recipe_forms)。
     */
    public function formsTableName(): string
    {
        return 'customs_'.$this->name.'_forms';
    }

    /**
     * カスタムフォームの入力値のテーブル名(例: customs_recipe_form_values)。
     */
    public function formValuesTableName(): string
    {
        return 'customs_'.$this->name.'_form_values';
    }

    /**
     * 本体を指す外部キーの列名(例: user_make_recipe_id)。
     */
    public function entryForeignKey(): string
    {
        return self::TABLE_PREFIX.$this->name.'_id';
    }

    /**
     * 詳細を指す外部キーの列名(例: user_make_recipe_detail_id)。
     */
    public function detailForeignKey(): string
    {
        return self::TABLE_PREFIX.$this->name.'_detail_id';
    }

    /**
     * カスタムフォームの項目定義を指す外部キーの列名(例: customs_recipe_form_id)。
     */
    public function formForeignKey(): string
    {
        return 'customs_'.$this->name.'_form_id';
    }

    /**
     * この種類で使うテーブル名の一覧(詳細は固定ページ型のみ)。
     *
     * @return list<string>
     */
    public function tableNames(): array
    {
        return array_values(array_filter([
            $this->tableName(),
            $this->hasDetails() ? $this->detailsTableName() : null,
            $this->formsTableName(),
            $this->formValuesTableName(),
        ]));
    }
}
