<?php

namespace App\Models;

use App\Enums\NavItemLinkType;
use App\Models\Concerns\HasSortOrder;
use Database\Factories\LayoutNavItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * レイアウトのナビメニューの部品(LayoutBlock)に並べる項目。
 */
#[Fillable(['layout_block_id', 'link_type', 'label', 'url', 'single_page_id', 'custom_page_type_id', 'sort_order'])]
class LayoutNavItem extends Model
{
    /** @use HasFactory<LayoutNavItemFactory> */
    use HasFactory, HasSortOrder, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'link_type' => NavItemLinkType::class,
        ];
    }

    /**
     * 項目が属するナビメニューの部品を取得する。
     */
    public function layoutBlock(): BelongsTo
    {
        return $this->belongsTo(LayoutBlock::class);
    }

    /**
     * リンク先の固定ページを取得する(リンク先が固定ページのときだけ)。
     */
    public function singlePage(): BelongsTo
    {
        return $this->belongsTo(SinglePage::class);
    }

    /**
     * リンク先のカスタムページの種類を取得する(リンク先がカスタムページの一覧のときだけ)。
     */
    public function customPageType(): BelongsTo
    {
        return $this->belongsTo(CustomPageType::class);
    }

    /**
     * 公開側に出す項目(表示名・パス・下の階層のページでも選択中にするか)。
     * リンク先の固定ページが公開期間外・削除済み、カスタムページの種類が削除済みなら null。
     * 固定ページは公開中かどうかを見るため、singlePage を published() で絞って読み込んでおく。
     *
     * @return array{label: string, path: string, prefix: bool}|null
     */
    public function toMenuItem(): ?array
    {
        return match ($this->link_type) {
            NavItemLinkType::Url => ['label' => (string) $this->label, 'path' => (string) $this->url, 'prefix' => false],
            NavItemLinkType::SinglePage => $this->singlePage
                ? ['label' => $this->label ?: $this->singlePage->title, 'path' => $this->singlePage->path, 'prefix' => false]
                : null,
            NavItemLinkType::CustomPageType => $this->customPageType
                ? ['label' => $this->label ?: $this->customPageType->label, 'path' => $this->customPageType->publicPath(), 'prefix' => true]
                : null,
        };
    }
}
