<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

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
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (in_array($value, self::ALLOWED_TABLE_NAMES, true)) {
            return;
        }

        if (is_string($value) && str_starts_with($value, self::USER_MADE_TABLE_PREFIX)) {
            return;
        }

        $fail(__('テーブル名は articles・single_pages・user_details のいずれか、または「user_make_」から始まる名前を指定してください。'));
    }
}
