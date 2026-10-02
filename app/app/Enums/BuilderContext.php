<?php

namespace App\Enums;

use App\Support\Builder\BlockRegistry;

/**
 * ページビルダーの内容を編集・検証する文脈(ページ・グローバルコンポーネント・独自コンポーネント)。文脈ごとに、
 * 一番外側に置けるブロックと、グローバルコンポーネント・独自コンポーネントのブロックを置けるか、差し替えられる項目(exposed)を持てるかが違う。
 * - ページ: 一番外側はセクションとグローバルコンポーネント。中に独自コンポーネントを置ける
 * - グローバルコンポーネント: 一番外側はセクションだけ。グローバルコンポーネントは置けない(入れ子が終わらなくなるため)
 * - 独自コンポーネント: 一番外側はカラムの中と同じブロック(コンテナ・行も)。どちらのコンポーネントも置けない。差し替えられる項目を持てる
 */
enum BuilderContext: string
{
    case Page = 'page';
    case GlobalComponent = 'global';
    case CustomComponent = 'custom';

    /**
     * 一番外側(ルート)に置けるブロック。
     *
     * @return list<string>
     */
    public function rootChildren(): array
    {
        return match ($this) {
            self::Page => BlockRegistry::ROOT_CHILDREN,
            self::GlobalComponent => ['section'],
            self::CustomComponent => BlockRegistry::CUSTOM_ROOT_CHILDREN,
        };
    }

    /**
     * この文脈で使えるブロックの種類か(コンポーネントの中にはコンポーネントのブロックを置けない)。
     */
    public function allowsBlock(string $type): bool
    {
        return match ($type) {
            'global' => $this === self::Page,
            'custom' => $this !== self::CustomComponent,
            default => true,
        };
    }

    /**
     * ノードに差し替えられる項目(exposed)を持てるか。
     */
    public function allowsExposed(): bool
    {
        return $this === self::CustomComponent;
    }
}
