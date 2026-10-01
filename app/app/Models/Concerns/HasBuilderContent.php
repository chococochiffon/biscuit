<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Date;

/**
 * ページビルダーの内容を、編集中(draft_content)と公開中(published_content。未公開なら null)に分けて持つモデル用の共通処理
 * (ページの内容 PageBuilder と、グローバルコンポーネント PageBuilderComponent)。published_at は最後に公開した日時。
 */
trait HasBuilderContent
{
    /**
     * 編集中の内容を公開中の内容にし、公開した日時を記録する(保存はしない)。内容は検証済みであること。
     */
    public function publish(): void
    {
        $this->published_content = $this->draft_content;
        $this->published_at = Date::now();
    }

    /**
     * 公開中の内容があるか。
     */
    public function isPublished(): bool
    {
        return $this->published_content !== null;
    }

    /**
     * 編集中の内容に、まだ公開していない変更があるか(未公開なら、何か置いてあれば変更ありとする)。
     */
    public function hasUnpublishedChanges(): bool
    {
        return $this->isPublished()
            ? $this->draft_content != $this->published_content
            : ($this->draft_content['children'] ?? []) !== [];
    }
}
