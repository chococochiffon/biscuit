<?php

namespace App\Http\Requests;

/**
 * グローバルコンポーネントの編集中の内容(下書き)の保存。ページの下書きと同じく BuilderValidator で検証するが、
 * 中にグローバルコンポーネントのブロックは置けない。
 */
class SavePageBuilderComponentRequest extends SavePageBuilderRequest
{
    /**
     * 内容にグローバルコンポーネントのブロックを置けるか。
     */
    protected function allowsGlobalComponents(): bool
    {
        return false;
    }
}
