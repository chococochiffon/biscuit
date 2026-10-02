<?php

namespace App\Enums;

/**
 * ページビルダーのコンポーネント(PageBuilderComponent)の種類。
 * - グローバル: どのページでも同じ中身のパーツ(ヘッダー・お問い合わせへの案内など)。ページの直下に「グローバルコンポーネント」のブロックで置く
 * - 独自: 使うたびに一部の項目(見出しの文字・画像など)を差し替えられる部品。ページのセクションなどの中に、パレットから部品ごとのブロックで置く
 */
enum BuilderComponentKind: string
{
    case Global = 'global';
    case Custom = 'custom';

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::Global => __('グローバルコンポーネント'),
            self::Custom => __('独自コンポーネント'),
        };
    }

    /**
     * この種類のコンポーネントの中身を編集・検証する文脈。
     */
    public function context(): BuilderContext
    {
        return match ($this) {
            self::Global => BuilderContext::GlobalComponent,
            self::Custom => BuilderContext::CustomComponent,
        };
    }
}
