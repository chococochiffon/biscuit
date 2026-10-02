<?php

namespace App\Models\Concerns;

use App\Models\PageBuilderVersion;
use App\Support\Builder\BuilderContent;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Date;

/**
 * ページビルダーの内容を、編集中(draft_content)と公開中(published_content。未公開なら null)に分けて持つモデル用の共通処理
 * (ページの内容 PageBuilder と、グローバルコンポーネント PageBuilderComponent)。published_at は最後に公開した日時。
 * 公開するたびに、公開した内容を版(PageBuilderVersion)として残す。
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
     * 公開した内容の版(新しい順に並べるときは latestVersions())。
     *
     * @return MorphMany<PageBuilderVersion, $this>
     */
    public function versions(): MorphMany
    {
        return $this->morphMany(PageBuilderVersion::class, 'versionable');
    }

    /**
     * 新しい順(公開した日時、同じなら id の大きい順)の版。
     *
     * @return MorphMany<PageBuilderVersion, $this>
     */
    public function latestVersions(): MorphMany
    {
        return $this->versions()->latest()->orderByDesc('id');
    }

    /**
     * 公開中の内容を版として残す(publish() して保存したあとに呼ぶ)。公開した管理者が分からなければ null。
     */
    public function recordVersion(?int $administratorId): PageBuilderVersion
    {
        return $this->versions()->create([
            'administrator_id' => $administratorId,
            'schema_version' => $this->schema_version,
            'content' => $this->published_content,
            'node_count' => iterator_count(BuilderContent::nodes($this->published_content ?? [])),
        ]);
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
