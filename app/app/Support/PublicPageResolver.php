<?php

namespace App\Support;

use App\Models\Article;
use App\Models\CustomPages\CustomPageEntry;
use App\Models\CustomPageType;
use App\Models\SinglePage;

/**
 * 公開側の URL のパスから、表示するページ(トップ・固定ページ・記事・カスタムページとその一覧)を解決する。
 * - /: トップ
 * - URL の先頭がカスタムページの種類(カスタム名の複数形)なら、その一覧(/recipes)か 1 件(/recipes/スラッグ。スラッグ未入力の記事型は id)。
 *   それより深い階層は該当なし
 * - それ以外: 公開期間内の固定ページ、なければ公開済みかつ公開期間内の記事
 * 公開されていないもの・論理削除したものは該当なし(null)として扱う。関連(詳細・タグなど)は読み込まないため、使う側で読み込む。
 */
class PublicPageResolver
{
    public function resolve(string $path): ?PublicPage
    {
        $path = self::normalizePath($path);

        if ($path === '/') {
            return new PublicPage(PublicPage::TYPE_TOP, $path);
        }

        $customPageType = CustomPageType::query()->get()->first(fn (CustomPageType $type) => str_starts_with($path.'/', $type->publicPath().'/'));

        if ($customPageType !== null) {
            return $this->resolveCustomPage($customPageType, $path);
        }

        $singlePage = SinglePage::query()->where('path', $path)->published()->first();

        if ($singlePage !== null) {
            return new PublicPage(PublicPage::TYPE_SINGLE_PAGE, $path, $singlePage);
        }

        $article = Article::query()->where('path', $path)->published()->first();

        return $article === null ? null : new PublicPage(PublicPage::TYPE_ARTICLE, $path, $article);
    }

    /**
     * パスの前後の / を揃える(例: about/ → /about、空文字 → /)。
     */
    public static function normalizePath(string $path): string
    {
        return '/'.trim($path, '/');
    }

    private function resolveCustomPage(CustomPageType $type, string $path): ?PublicPage
    {
        $rest = trim(substr($path, strlen($type->publicPath())), '/');

        if ($rest === '') {
            return new PublicPage(PublicPage::TYPE_CUSTOM_PAGE_LIST, $path, customPageType: $type);
        }

        if (str_contains($rest, '/')) {
            return null;
        }

        $entry = CustomPageEntry::publishedQueryFor($type)
            ->where(fn ($query) => $query
                ->where('slug', $rest)
                ->when(! $type->hasDetails() && ctype_digit($rest), fn ($query) => $query->orWhere(fn ($query) => $query->whereNull('slug')->whereKey($rest))))
            ->first();

        return $entry === null ? null : new PublicPage(PublicPage::TYPE_CUSTOM_PAGE, $path, $entry, $type);
    }
}
