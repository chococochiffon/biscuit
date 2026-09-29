<?php

namespace App\Models;

use App\Enums\CallContentPlace;
use App\Enums\CallType;
use App\Enums\LayoutBlockType;
use App\Enums\LayoutRegion;
use App\Models\Concerns\HasSortOrder;
use Database\Factories\LayoutBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 公開側のレイアウトの領域(ヘッダー・サイドバー・フッター)に置く部品。
 * 呼び出しコンテンツの部品は、呼び出しコンテンツの「その他」の設置場所と同じ組み合わせ(CallType のマトリクス)で
 * 呼び出し方・データ種別を選び、CallContentResolver で実データを解決する。
 */
#[Fillable(['region', 'block_type', 'title', 'subtitle', 'call_type', 'content_model_relation_id', 'view_count', 'content', 'sort_order'])]
class LayoutBlock extends Model
{
    /** @use HasFactory<LayoutBlockFactory> */
    use HasFactory, HasSortOrder, SoftDeletes;

    /**
     * 呼び出しコンテンツの部品で、選べる組み合わせと実データの取得条件に使う設置場所。
     */
    public const CALL_CONTENT_PLACE = CallContentPlace::Others;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'region' => LayoutRegion::class,
            'block_type' => LayoutBlockType::class,
            'call_type' => CallType::class,
        ];
    }

    /**
     * 呼び出しコンテンツの部品のデータ種別の紐付けを取得する。
     */
    public function contentModelRelation(): BelongsTo
    {
        return $this->belongsTo(ContentModelRelation::class);
    }

    /**
     * 呼び出しコンテンツの部品を、実データの解決(CallContentResolver・CallContentResource)に渡す
     * 保存しない呼び出しコンテンツにする(呼び出しコンテンツの部品でなければ null)。
     */
    public function toCallContent(): ?CallContent
    {
        if ($this->block_type !== LayoutBlockType::CallContent) {
            return null;
        }

        $callContent = new CallContent([
            'call_type' => $this->call_type,
            'call_name' => $this->title ?? '',
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'content_model_relation_id' => $this->content_model_relation_id,
            'view_count' => $this->view_count,
            'place' => self::CALL_CONTENT_PLACE,
        ]);

        return $callContent->setRelation('contentModelRelation', $this->contentModelRelation);
    }
}
