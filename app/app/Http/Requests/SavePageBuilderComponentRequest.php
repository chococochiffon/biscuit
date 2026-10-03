<?php

namespace App\Http\Requests;

use App\Enums\BuilderContext;
use App\Models\PageBuilderComponent;

/**
 * コンポーネントの編集中の内容(下書き)の保存。ページの下書きと同じく BuilderValidator で検証するが、
 * コンポーネントの種類の文脈(グローバル・独自)で検証する(中にグローバルコンポーネントは置けない。独自コンポーネントは差し替えられる項目を持てる)。
 */
class SavePageBuilderComponentRequest extends SavePageBuilderRequest
{
    /**
     * 内容を検証する文脈(ルートのコンポーネントの種類)。
     */
    protected function context(): BuilderContext
    {
        $component = $this->route('pageBuilderComponent');

        return $component instanceof PageBuilderComponent ? $component->kind->context() : BuilderContext::GlobalComponent;
    }
}
