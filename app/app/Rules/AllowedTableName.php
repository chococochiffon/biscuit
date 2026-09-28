<?php

namespace App\Rules;

use App\Models\CustomPageType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Schema;

class AllowedTableName implements ValidationRule
{
    /**
     * table_nameとして許可する固定値(ほかに、カスタムページの種類の本体のテーブルを許可する)。
     *
     * @var array<int, string>
     */
    public const ALLOWED_TABLE_NAMES = ['articles', 'single_pages', 'user_details', 'gallery_images', 'question_answers'];

    /**
     * 選択肢として提示できるテーブル名を取得する。
     * 固定許可値(ALLOWED_TABLE_NAMES)は未作成のテーブル(例: user_details)も含めて常に候補とし、
     * カスタムページは登録済みの種類(論理削除済みは除く)の本体のテーブル(例: user_make_recipes)だけを候補にする
     * (詳細・カスタムフォームのテーブルは、呼び出しコンテンツのどの組み合わせにも使えないため出さない)。
     *
     * @return array<int, string>
     */
    public static function availableTables(): array
    {
        return collect(self::ALLOWED_TABLE_NAMES)
            ->merge(self::customPageTables())
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::isAllowed((string) $value)) {
            return;
        }

        $fail(__('テーブル名は articles・single_pages・user_details・gallery_images・question_answers のいずれか、またはカスタムページの本体のテーブル(user_make_○○)を指定してください。'));
    }

    /**
     * 許可対象のテーブル名かどうかを判定する。
     */
    private static function isAllowed(string $table): bool
    {
        return in_array($table, self::ALLOWED_TABLE_NAMES, true) || in_array($table, self::customPageTables(), true);
    }

    /**
     * 登録済みのカスタムページの種類(論理削除済みは除く)の本体のテーブルのうち、実際にあるもの。
     *
     * @return list<string>
     */
    private static function customPageTables(): array
    {
        return CustomPageType::query()->ordered()->get()
            ->map(fn (CustomPageType $type) => $type->tableName())
            ->filter(fn (string $table) => Schema::hasTable($table))
            ->values()
            ->all();
    }
}
