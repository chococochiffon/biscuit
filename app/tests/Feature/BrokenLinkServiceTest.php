<?php

namespace Tests\Feature;

use App\Enums\ArticleApprovalStatus;
use App\Enums\CustomPageBaseType;
use App\Enums\NavItemLinkType;
use App\Models\Article;
use App\Models\CustomPages\CustomPageEntry;
use App\Models\CustomPageType;
use App\Models\LayoutBlock;
use App\Models\LayoutNavItem;
use App\Models\SinglePage;
use App\Models\SinglePageDetail;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\UserDetail;
use App\Services\BrokenLinkService;
use App\Support\CustomPages\CustomPageSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 本文などのリンク切れのチェック(BrokenLinkService)。
 */
class BrokenLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['app.url' => 'http://biscuit.test', 'app.front_url' => 'https://chococo.test']);
    }

    private function service(): BrokenLinkService
    {
        return app(BrokenLinkService::class);
    }

    public function test_internal_links_to_published_pages_are_not_broken(): void
    {
        SinglePage::factory()->create(['slug' => 'about', 'publication_start_datetime' => now()->subDay()]);
        Article::factory()->published()->create(['slug' => 'hello', 'parent_path' => 'news', 'publication_start_datetime' => now()->subDay()]);

        $html = '<p><a href="/about">会社概要</a> <a href="https://chococo.test/news/hello?utm=1#top">記事</a> <a href="/">トップ</a></p>';

        $this->assertSame([], $this->service()->brokenLinksIn($html));
    }

    public function test_links_to_missing_or_unpublished_pages_are_broken(): void
    {
        Article::factory()->create(['slug' => 'draft', 'parent_path' => 'news']);
        SinglePage::factory()->create(['slug' => 'later', 'publication_start_datetime' => now()->addDay()]);

        $html = '<a href="/news/draft">下書き</a><a href="/later">予約</a><a href="https://chococo.test/missing">なし</a><a href="/missing">なし</a>';

        $this->assertSame(['/news/draft', '/later', 'https://chococo.test/missing', '/missing'], $this->service()->brokenLinksIn($html));
    }

    public function test_external_links_anchors_and_other_schemes_are_ignored(): void
    {
        $html = '<a href="https://example.com/missing">外部</a><a href="#section">ページ内</a><a href="mailto:a@example.com">メール</a>'
            .'<a href="tel:0000">電話</a><a href="javascript:void(0)">JS</a><a href="relative">相対</a><a href="">空</a>';

        $this->assertSame([], $this->service()->brokenLinksIn($html));
    }

    public function test_front_url_from_site_setting_is_treated_as_internal(): void
    {
        SiteSetting::factory()->create(['front_url' => 'https://www.example.jp']);

        $this->assertSame(['https://www.example.jp/missing'], $this->service()->brokenLinksIn('<a href="https://www.example.jp/missing">x</a>'));
    }

    public function test_static_front_pages_are_not_broken(): void
    {
        $html = '<a href="/gallery">ギャラリー</a><a href="/faq">FAQ</a><a href="/mypage/articles/new">投稿</a><a href="/articles">記事一覧</a>';

        $this->assertSame([], $this->service()->brokenLinksIn($html));
        $this->assertSame(['/articles/missing', '/gallery/missing'], $this->service()->brokenLinksIn('<a href="/articles/missing">x</a><a href="/gallery/missing">x</a>'));
    }

    public function test_author_pages_are_broken_unless_the_profile_is_public(): void
    {
        $public = User::factory()->create();
        UserDetail::factory()->for($public)->create(['view_flag' => true]);
        $private = User::factory()->create();
        UserDetail::factory()->for($private)->create(['view_flag' => false]);

        $html = "<a href=\"/authors/{$public->id}\">公開</a><a href=\"/authors/{$private->id}\">非公開</a><a href=\"/authors/999\">なし</a>";

        $this->assertSame(["/authors/{$private->id}", '/authors/999'], $this->service()->brokenLinksIn($html));
    }

    public function test_missing_storage_files_are_broken(): void
    {
        Storage::disk('public')->put('image/content/exists.png', 'x');

        $html = '<img src="http://biscuit.test/storage/image/content/exists.png"><img src="http://biscuit.test/storage/image/content/missing.png">'
            .'<a href="/storage/image/content/gone.pdf">資料</a>';

        $this->assertSame(['http://biscuit.test/storage/image/content/missing.png', '/storage/image/content/gone.pdf'], $this->service()->brokenLinksIn($html));
    }

    public function test_all_collects_contents_with_broken_links(): void
    {
        $article = Article::factory()->create(['title' => '記事A', 'content' => '<a href="/missing-a">x</a>']);
        Article::factory()->create(['content' => '<a href="https://example.com">x</a>']);
        $page = SinglePage::factory()->create(['title' => '固定B', 'publication_start_datetime' => now()->subDay()]);
        SinglePageDetail::factory()->for($page)->create(['contents' => '<a href="/missing-b">x</a>']);
        SinglePageDetail::factory()->for($page)->create(['contents' => '<a href="/missing-b">x</a><img src="/storage/none.png">']);
        LayoutBlock::factory()->freeText('<a href="/missing-c">x</a>')->create(['title' => 'お知らせ']);
        LayoutNavItem::factory()->create(['label' => 'リンク', 'url' => '/missing-d']);
        LayoutNavItem::factory()->create(['label' => '外部', 'url' => 'https://example.com']);
        LayoutNavItem::factory()->create([
            'label' => '予約のページ',
            'link_type' => NavItemLinkType::SinglePage,
            'url' => null,
            'single_page_id' => SinglePage::factory()->create(['publication_start_datetime' => now()->addDay()])->id,
        ]);
        $type = CustomPageType::factory()->create(['name' => 'recipe', 'label' => 'レシピ', 'base_type' => CustomPageBaseType::Article]);
        (new CustomPageSchema)->create($type);
        $entry = CustomPageEntry::queryFor($type)->create([
            'title' => '肉じゃが',
            'content' => '<a href="/missing-e">x</a>',
            'approval' => ArticleApprovalStatus::Draft,
        ]);

        $items = collect($this->service()->all()['items'])->keyBy('title');

        $this->assertSame(['記事A', '固定B', '肉じゃが', 'お知らせ', 'リンク', '予約のページ'], $items->keys()->all());
        $this->assertSame(route('admin.articles.edit', $article), $items['記事A']['edit_url']);
        $this->assertSame(['/missing-b', '/storage/none.png'], $items['固定B']['links']);
        $this->assertSame(route('admin.custom-pages.entries.edit', [$type, $entry->id]), $items['肉じゃが']['edit_url']);
        $this->assertSame('レシピ', $items['肉じゃが']['type']);
        $this->assertSame(route('admin.layouts.edit'), $items['お知らせ']['edit_url']);
        $this->assertSame(['公開されていない固定ページ'], $items['予約のページ']['links']);
    }

    public function test_all_is_cached(): void
    {
        Article::factory()->create(['content' => '<a href="/missing">x</a>']);
        $this->assertCount(1, $this->service()->all()['items']);

        Article::factory()->create(['content' => '<a href="/missing">x</a>']);
        $this->assertCount(1, $this->service()->all()['items']);

        cache()->forget(BrokenLinkService::CACHE_KEY);
        $this->assertCount(2, $this->service()->all()['items']);
    }
}
