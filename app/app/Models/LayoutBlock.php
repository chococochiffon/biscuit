<?php

namespace App\Models;

use App\Enums\CallContentPlace;
use App\Enums\CallType;
use App\Enums\LayoutBlockType;
use App\Enums\LayoutRegion;
use App\Models\Concerns\HasSortOrder;
use App\Support\CallContent\SinglePageContentSource;
use Database\Factories\LayoutBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 公開側のレイアウトの領域(ヘッダー・サイドバー・フッター)に置く部品。
 * 呼び出しコンテンツの部品は、設置場所「レイアウトの部品」(CallContentPlace::Layout)の組み合わせ(CallType のマトリクス)で
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
    public const CALL_CONTENT_PLACE = CallContentPlace::Layout;

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
     * ナビメニューの項目を自動で並べるとき(項目が未登録のとき)に、公開側(chococo)のページへのリンクとして足す項目。
     */
    private const AUTO_NAV_FRONT_PAGES = [
        'articles' => ['label' => 'Articles', 'path' => '/articles'],
        'gallery' => ['label' => 'Gallery', 'path' => '/gallery'],
        'faq' => ['label' => 'FAQ', 'path' => '/faq'],
    ];

    /**
     * ナビメニューの部品に並べる項目を取得する。
     */
    public function navItems(): HasMany
    {
        return $this->hasMany(LayoutNavItem::class);
    }

    /**
     * ナビメニューの部品で公開側に出す項目を返す。
     * 項目を登録していればその順に(リンク先が公開されていない項目は除く)、未登録なら Home・固定ページ(リンクリスト表示対象)・
     * Articles・カスタムページの種類の一覧・Gallery・FAQ を自動で並べる。
     * 登録した項目を使う場合は、navItems(並び順)と navItems.singlePage(published() で絞る)・navItems.customPageType を読み込んでおく。
     *
     * @return list<array{label: string, path: string, prefix: bool}>
     */
    public function navMenuItems(): array
    {
        if ($this->navItems->isNotEmpty()) {
            return $this->navItems->map(fn (LayoutNavItem $item) => $item->toMenuItem())->filter()->values()->all();
        }

        return [
            ['label' => 'Home', 'path' => '/', 'prefix' => false],
            ...(new SinglePageContentSource)->getLinkList()
                ->map(fn (SinglePage $page) => ['label' => $page->title, 'path' => $page->path, 'prefix' => false])
                ->all(),
            [...self::AUTO_NAV_FRONT_PAGES['articles'], 'prefix' => false],
            ...CustomPageType::query()->ordered()->get()
                ->map(fn (CustomPageType $type) => ['label' => $type->label, 'path' => $type->publicPath(), 'prefix' => true])
                ->all(),
            [...self::AUTO_NAV_FRONT_PAGES['gallery'], 'prefix' => false],
            [...self::AUTO_NAV_FRONT_PAGES['faq'], 'prefix' => false],
        ];
    }

    /**
     * サイトのナビメニューの項目(ページビルダーのナビゲーションのブロック用)。ナビメニューの部品のうちヘッダーのもの(なければほかの領域のもの)の
     * 項目を使い、ナビメニューの部品がなければ navMenuItems() と同じく自動で並べる。
     *
     * @return list<array{label: string, path: string, prefix: bool}>
     */
    public static function siteNavMenuItems(): array
    {
        $block = self::query()
            ->where('block_type', LayoutBlockType::NavMenu)
            ->with([
                'navItems' => fn ($query) => $query->ordered(),
                'navItems.singlePage' => fn ($query) => $query->published(),
                'navItems.customPageType',
            ])
            ->get()
            ->sortBy(fn (self $block) => [$block->region === LayoutRegion::Header ? 0 : 1, $block->sort_order, $block->id])
            ->first();

        return ($block ?? new self)->navMenuItems();
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
