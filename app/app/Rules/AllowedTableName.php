<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Schema;

class AllowedTableName implements ValidationRule
{
    /**
     * table_nameとして許可する固定値(ユーザーが動的作成したテーブルは"user_make_"接頭辞で許可する)。
     *
     * @var array<int, string>
     */
    public const ALLOWED_TABLE_NAMES = ['articles', 'single_pages', 'user_details'];

    /**
     * ユーザーが動的作成したテーブルを示す接頭辞。
     */
    public const USER_MADE_TABLE_PREFIX = 'user_make_';

    /**
     * 選択肢として提示できるテーブル名を取得する。
     * 固定許可値(ALLOWED_TABLE_NAMES)は未作成のテーブル(例: user_details)も含めて常に候補とし、
     * "user_make_"接頭辞のテーブルは実際にデータベースへ存在するものだけを動的に取得する。
     *
     * @return array<int, string>
     */
    public static function availableTables(): array
    {
        $userMadeTables = collect(Schema::getTableListing(schemaQualified: false))
            ->filter(fn (string $table) => str_starts_with($table, self::USER_MADE_TABLE_PREFIX));

        return collect(self::ALLOWED_TABLE_NAMES)
            ->merge($userMadeTables)
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

        $fail(__('テーブル名は articles・single_pages・user_details のいずれか、または「user_make_」から始まる名前を指定してください。'));
    }

    /**
     * 許可対象のテーブル名かどうかを判定する。
     */
    private static function isAllowed(string $table): bool
    {
        return in_array($table, self::ALLOWED_TABLE_NAMES, true) || str_starts_with($table, self::USER_MADE_TABLE_PREFIX);
    }
}
