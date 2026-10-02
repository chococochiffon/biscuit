<?php

namespace App\Models;

use App\Models\Concerns\StoresReadableJson;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ページビルダーの版(公開した内容の写し)。ページ(PageBuilder)・グローバルコンポーネント(PageBuilderComponent)を公開するたびに
 * Models\Concerns\HasBuilderContent::recordVersion() で 1 件残し、エディタの「版の履歴」から下書きに読み込む。
 * 対象の種類(versionable_type)は AppServiceProvider の morphMap の名前(page_builder・page_builder_component)。
 */
#[Fillable(['administrator_id', 'schema_version', 'content', 'node_count'])]
class PageBuilderVersion extends Model
{
    use SoftDeletes, StoresReadableJson;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'content' => 'array',
            'node_count' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 公開した管理者(削除された管理者も含む)。
     *
     * @return BelongsTo<Administrator, $this>
     */
    public function administrator(): BelongsTo
    {
        return $this->belongsTo(Administrator::class)->withTrashed();
    }
}
