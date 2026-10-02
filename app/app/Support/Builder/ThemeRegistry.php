<?php

namespace App\Support\Builder;

/**
 * ページビルダーのテーマ(PageBuilderTheme)の項目の定義。テーマはビルダーのブロックにだけ効く。
 *
 * - 色: ブロックの色のスタイルに「theme:名前」(例: theme:primary)を入れると、テーマのその色を使う(テーマを変えると全ページに反映される)。
 *   ボタンのブロックの見た目(メイン・サブ)もテーマの primary・secondary に従う。既定はメイン・サブが Bootstrap と同じ色で、
 *   テーマを登録しなければ今までの見た目のまま。
 * - フォント: 見出し・本文のフォントを、決めた選択肢(日本語に対応した Google Fonts)から選ぶ。選ばなければサイトの既定のまま。
 */
final class ThemeRegistry
{
    /**
     * テーマの色(名前 → 表示名・既定値)。表示名は日本語の原文。
     *
     * @var array<string, array{label: string, default: string}>
     */
    public const COLORS = [
        'primary' => ['label' => 'メイン', 'default' => '#0d6efd'],
        'secondary' => ['label' => 'サブ', 'default' => '#6c757d'],
        'accent' => ['label' => 'アクセント', 'default' => '#e99540'],
        'text' => ['label' => '文字', 'default' => '#212529'],
        'light' => ['label' => '淡い色', 'default' => '#f8f9fa'],
    ];

    /**
     * 選べるフォント(キー → 表示名・CSS の font-family・Google Fonts の family の指定)。
     *
     * @var array<string, array{label: string, family: string, google: string}>
     */
    public const FONTS = [
        'zen-maru-gothic' => ['label' => 'Zen Maru Gothic(丸ゴシック)', 'family' => "'Zen Maru Gothic', sans-serif", 'google' => 'Zen+Maru+Gothic:wght@400;700'],
        'noto-sans-jp' => ['label' => 'Noto Sans JP(ゴシック)', 'family' => "'Noto Sans JP', sans-serif", 'google' => 'Noto+Sans+JP:wght@400;700'],
        'noto-serif-jp' => ['label' => 'Noto Serif JP(明朝)', 'family' => "'Noto Serif JP', serif", 'google' => 'Noto+Serif+JP:wght@400;700'],
        'm-plus-rounded-1c' => ['label' => 'M PLUS Rounded 1c(丸ゴシック)', 'family' => "'M PLUS Rounded 1c', sans-serif", 'google' => 'M+PLUS+Rounded+1c:wght@400;700'],
        'kosugi-maru' => ['label' => 'Kosugi Maru(丸ゴシック)', 'family' => "'Kosugi Maru', sans-serif", 'google' => 'Kosugi+Maru'],
        'shippori-mincho' => ['label' => 'Shippori Mincho(明朝)', 'family' => "'Shippori Mincho', serif", 'google' => 'Shippori+Mincho:wght@400;700'],
    ];

    /**
     * 色のスタイルに入れるテーマの色の値(theme:名前)の形。
     */
    public const COLOR_TOKEN_PATTERN = '/\Atheme:(?:primary|secondary|accent|text|light)\z/';

    /**
     * 既定のテーマの色。
     *
     * @return array<string, string>
     */
    public static function defaultColors(): array
    {
        return array_map(fn (array $color) => $color['default'], self::COLORS);
    }

    /**
     * フォントを、エディタ・公開側に渡す形(font-family と読み込む CSS の URL)にする。選んでいなければ null。
     *
     * @return array{key: string, family: string, href: string}|null
     */
    public static function font(?string $key): ?array
    {
        $font = self::FONTS[$key] ?? null;

        return $font === null ? null : [
            'key' => $key,
            'family' => $font['family'],
            'href' => 'https://fonts.googleapis.com/css2?family='.$font['google'].'&display=swap',
        ];
    }
}
