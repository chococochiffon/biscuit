<?php

namespace App\Support\Builder;

/**
 * ページビルダーのブロックに使えるスタイルと、その値の形。
 * CSS の文字列はそのまま保存せず、プロパティ(camelCase)ごとに許した形の値だけを受け付ける。
 * url(...) や expression などを書ける自由な値は、どのスタイルでも受け付けない(背景画像はスタイルではなく props で持つ)。
 */
final class StyleRegistry
{
    /**
     * デスクトップの値を端末ごとに上書きできる端末(responsive のキー)。
     *
     * @var list<string>
     */
    public const DEVICES = ['tablet', 'mobile'];

    /**
     * 長さ(数値 + 単位、0、auto)。
     */
    private const LENGTH_PATTERN = '/\A(?:0|auto|\d{1,4}(?:\.\d{1,2})?(?:px|rem|em|%|vh|vw))\z/';

    /**
     * 色(#rgb・#rrggbb・#rrggbbaa)。テーマの色(theme:primary など。ThemeRegistry)も受け付ける。
     */
    private const COLOR_PATTERN = '/\A#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\z/';

    /**
     * 単位のない数値(行の高さ)。
     */
    private const NUMBER_PATTERN = '/\A\d(?:\.\d{1,2})?\z/';

    /**
     * スタイルごとの値の形(length・color・number、または選べる値の一覧)。
     *
     * @return array<string, string|list<string>>
     */
    public static function kinds(): array
    {
        return [
            'marginTop' => 'length',
            'marginBottom' => 'length',
            'paddingTop' => 'length',
            'paddingBottom' => 'length',
            'paddingLeft' => 'length',
            'paddingRight' => 'length',
            'width' => 'length',
            'maxWidth' => 'length',
            'minHeight' => 'length',
            'fontSize' => 'length',
            'borderRadius' => 'length',
            'borderWidth' => 'length',
            'lineHeight' => 'number',
            'color' => 'color',
            'backgroundColor' => 'color',
            'borderColor' => 'color',
            'textAlign' => ['left', 'center', 'right', 'justify'],
            'fontWeight' => ['300', '400', '500', '600', '700', '800'],
            'borderStyle' => ['none', 'solid', 'dashed', 'dotted', 'double'],
        ];
    }

    /**
     * スタイルの値が、そのスタイルで許した形か。
     */
    public static function isValid(string $name, mixed $value): bool
    {
        $kind = self::kinds()[$name] ?? null;

        if ($kind === null || ! is_string($value)) {
            return false;
        }

        return match ($kind) {
            'length' => preg_match(self::LENGTH_PATTERN, $value) === 1,
            'color' => preg_match(self::COLOR_PATTERN, $value) === 1 || preg_match(ThemeRegistry::COLOR_TOKEN_PATTERN, $value) === 1,
            'number' => preg_match(self::NUMBER_PATTERN, $value) === 1,
            default => in_array($value, $kind, true),
        };
    }
}
