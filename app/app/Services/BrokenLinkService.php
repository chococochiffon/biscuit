<?php

namespace App\Services;

use App\Enums\LayoutBlockType;
use App\Enums\NavItemLinkType;
use App\Models\Article;
use App\Models\CustomPages\CustomPageDetail;
use App\Models\CustomPages\CustomPageEntry;
use App\Models\CustomPageType;
use App\Models\LayoutBlock;
use App\Models\LayoutNavItem;
use App\Models\SinglePage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\PublicPageResolver;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 本文などのリンク切れを探す(管理画面とマイページのダッシュボードのコンテンツチェック)。
 * 調べるのはサイト内のリンクと画像だけで、公開側(chococo)のパスは公開中のページに解決できるか(PublicPageResolver と chococo の固定のページ)、
 * /storage/ の画像・ファイルは公開ディスクにあるかを見る。外部のサイトへのリンクは通信が必要なため調べない。
 * 対象は記事・固定ページ(詳細)・カスタムページ(本文と詳細)の本文、レイアウトの自由テキスト、ナビメニューの項目(論理削除したものを除く)。
 */
class BrokenLinkService
{
    public const CACHE_KEY = 'dashboard.broken_links';

    public const CACHE_SECONDS = 300;

    /**
     * 調べた結果(パスごと)。1 回の走査の中で同じパスを何度も解決しない。
     *
     * @var array<string, bool>
     */
    private array $checked = [];

    public function __construct(private readonly PublicPageResolver $resolver) {}

    /**
     * リンク切れのあるコンテンツの一覧(管理画面のダッシュボード)。走査が重いため CACHE_SECONDS 秒キャッシュする。
     *
     * @return array{items: list<array{type: string, title: string, edit_url: string, links: list<string>}>, calculated_at: string}
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => [
            'items' => $this->collectAll()->values()->all(),
            'calculated_at' => now()->format('Y/m/d H:i'),
        ]);
    }

    /**
     * 記事のうち、本文にリンク切れのあるものと切れているリンク(マイページのダッシュボードで自分の記事に使う)。
     *
     * @param  Builder<Article>|HasMany<Article, User>  $articles
     * @return Collection<int, array{article: Article, links: list<string>}>
     */
    public function inArticles(Builder|HasMany $articles): Collection
    {
        return $articles->latest('updated_at')->latest('id')->get()
            ->map(fn (Article $article) => ['article' => $article, 'links' => $this->brokenLinksIn($article->content)])
            ->filter(fn (array $row) => $row['links'] !== [])
            ->values();
    }

    /**
     * HTML の中のサイト内のリンク(a の href)と画像(img の src)のうち、切れているものの URL を返す(同じ URL は 1 つにまとめる)。
     *
     * @return list<string>
     */
    public function brokenLinksIn(?string $html): array
    {
        if (blank($html) || (! str_contains($html, 'href') && ! str_contains($html, 'src'))) {
            return [];
        }

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET);
        $urls = [];

        // 本文の中の順番で並べる
        foreach ((new DOMXPath($document))->query('//a[@href] | //img[@src]') as $element) {
            $urls[] = trim($element->getAttribute($element->nodeName === 'a' ? 'href' : 'src'));
        }

        return array_values(array_unique(array_filter($urls, fn (string $url) => $this->isBroken($url))));
    }

    /**
     * サイト内の URL が切れているか。外部のサイト・ページ内リンク・mailto: などは切れていないものとして扱う。
     */
    public function isBroken(string $url): bool
    {
        $path = $this->internalPath($url);

        if ($path === null) {
            return false;
        }

        return ! ($this->checked[$path] ??= $this->exists($path));
    }

    /**
     * @return Collection<int, array{type: string, title: string, edit_url: string, links: list<string>}>
     */
    private function collectAll(): Collection
    {
        $items = collect();

        Article::query()->latest('updated_at')->latest('id')->each(function (Article $article) use ($items) {
            $this->push($items, __('記事'), $article->title, route('admin.articles.edit', $article), $this->brokenLinksIn($article->content));
        });

        SinglePage::query()->with('details')->latest('updated_at')->latest('id')->each(function (SinglePage $page) use ($items) {
            $links = $page->details->flatMap(fn ($detail) => $this->brokenLinksIn($detail->contents))->unique()->values()->all();
            $this->push($items, __('固定ページ'), $page->title, route('admin.single-pages.edit', $page), $links);
        });

        foreach (CustomPageType::query()->get() as $type) {
            CustomPageEntry::orderedQueryFor($type)->each(function (CustomPageEntry $entry) use ($items, $type) {
                $html = $type->hasDetails()
                    ? CustomPageDetail::queryFor($type)->where($type->entryForeignKey(), $entry->id)->pluck('contents')->implode("\n")
                    : $entry->content;

                $this->push($items, $type->label, (string) $entry->title, route('admin.custom-pages.entries.edit', [$type, $entry->id]), $this->brokenLinksIn($html));
            });
        }

        LayoutBlock::query()->where('block_type', LayoutBlockType::FreeText)->each(function (LayoutBlock $block) use ($items) {
            $this->push($items, __('レイアウト'), $block->title ?: $block->block_type->label(), route('admin.layouts.edit'), $this->brokenLinksIn($block->content));
        });

        LayoutNavItem::query()->with(['singlePage' => fn ($query) => $query->published()])->each(function (LayoutNavItem $item) use ($items) {
            $links = match ($item->link_type) {
                NavItemLinkType::Url => $this->isBroken((string) $item->url) ? [(string) $item->url] : [],
                NavItemLinkType::SinglePage => $item->singlePage === null ? [__('公開されていない固定ページ')] : [],
                default => [],
            };

            $this->push($items, __('ナビメニュー'), (string) ($item->label ?: $item->singlePage?->title ?: '—'), route('admin.layouts.edit'), $links);
        });

        return $items;
    }

    /**
     * @param  Collection<int, array{type: string, title: string, edit_url: string, links: list<string>}>  $items
     * @param  list<string>  $links
     */
    private function push(Collection $items, string $type, string $title, string $editUrl, array $links): void
    {
        if ($links !== []) {
            $items->push(['type' => $type, 'title' => $title, 'edit_url' => $editUrl, 'links' => $links]);
        }
    }

    /**
     * サイト内の URL ならパス(クエリ・フラグメントを除く)を、外部のサイトなどなら null を返す。
     * 公開側(サイト設定の「フロントの URL」)と biscuit(APP_URL。/storage/ の画像)のホストの URL と、/ から始まる相対 URL をサイト内とする。
     */
    private function internalPath(string $url): ?string
    {
        if ($url === '' || str_starts_with($url, '#') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) && ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        if (preg_match('#^https?://#i', $url)) {
            $host = parse_url($url, PHP_URL_HOST);
            $internalHosts = array_filter([parse_url(SiteSetting::frontUrl(), PHP_URL_HOST), parse_url((string) config('app.url'), PHP_URL_HOST)]);

            if (! in_array($host, $internalHosts, true)) {
                return null;
            }
        } elseif (! str_starts_with($url, '/')) {
            return null;
        }

        return rawurldecode((string) parse_url($url, PHP_URL_PATH)) ?: '/';
    }

    /**
     * サイト内のパスが存在するか(公開中のページ・chococo の固定のページ・公開している投稿者ページ・公開ディスクのファイル)。
     */
    private function exists(string $path): bool
    {
        if (str_starts_with($path, '/storage/')) {
            return Storage::disk('public')->exists(Str::after($path, '/storage/'));
        }

        $path = PublicPageResolver::normalizePath($path);

        foreach (config('biscuit.front_static_paths') as $staticPath) {
            $isStaticPage = str_ends_with($staticPath, '/*')
                ? str_starts_with($path, Str::beforeLast($staticPath, '/*').'/')
                : $path === $staticPath;

            if ($isStaticPage) {
                return true;
            }
        }

        if (preg_match('#^/'.User::AUTHOR_PATH_PREFIX.'/(\d+)$#', $path, $matches)) {
            return (bool) User::query()->with('detail')->find($matches[1])?->hasPublicProfile();
        }

        return $this->resolver->resolve($path) !== null;
    }
}
