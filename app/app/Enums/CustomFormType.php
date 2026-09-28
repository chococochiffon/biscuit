<?php

namespace App\Enums;

enum CustomFormType: int
{
    case Text = 0;
    case Date = 1;
    case Textarea = 2;
    case Email = 3;
    case Select = 4;
    case Radio = 5;
    case Checkbox = 6;

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Text => __('テキスト'),
            self::Date => __('日付'),
            self::Textarea => __('テキストエリア'),
            self::Email => __('メールアドレス'),
            self::Select => __('プルダウン'),
            self::Radio => __('ラジオ'),
            self::Checkbox => __('チェックボックス'),
        };
    }

    /**
     * 選択肢(customs_form_options)を持つ入力形式かどうか(プルダウン・ラジオ・チェックボックス)。
     */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::Radio, self::Checkbox], true);
    }
}
