<?php

namespace App\Support;

use App\Models\Article;
use App\Models\CustomPages\CustomPageEntry;
use App\Models\CustomPageType;
use App\Models\SinglePage;

/**
 * 公開側の URL のパスから解決したページ(Support\PublicPageResolver が返す)。
 * パス解決 API(API\ResolveController)と PV の記録(API\PageViewController)で共通に使う。
 */
final class PublicPage
{
    public const TYPE_TOP = 'top';

    public const TYPE_SINGLE_PAGE = 'single_page';

    public const TYPE_ARTICLE = 'article';

    public const TYPE_CUSTOM_PAGE_LIST = 'custom_page_list';

    public const TYPE_CUSTOM_PAGE = 'custom_page';

    /**
     * @param  string  $type  ページの種類(TYPE_*。パス解決 API の type と同じ値)
     * @param  string  $path  正規化したパス(先頭に / を付け、末尾の / を除いたもの。トップは /)
     * @param  Article|SinglePage|CustomPageEntry|null  $content  本文(トップ・カスタムページの一覧は null)
     */
    public function __construct(
        public readonly string $type,
        public readonly string $path,
        public readonly Article|SinglePage|CustomPageEntry|null $content = null,
        public readonly ?CustomPageType $customPageType = null,
    ) {}

    /**
     * PV などの記録に使うコンテンツの種類(例: article・single_page・top。
     * カスタムページは custom_page:種類の名前、その一覧は custom_page_list:種類の名前。監査ログの対象の種類と同じ書き方)。
     */
    public function contentType(): string
    {
        return $this->customPageType === null ? $this->type : $this->type.':'.$this->customPageType->name;
    }

    /**
     * コンテンツの id(トップ・カスタムページの一覧は null)。
     */
    public function contentId(): ?int
    {
        return $this->content?->getKey();
    }
}
