<?php

namespace Tests\Feature\API;

use App\Enums\ArticleApprovalStatus;
use App\Enums\CallContentPlace;
use App\Enums\CallContentType;
use App\Enums\CallType;
use App\Models\Article;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use App\Models\SinglePage;
use App\Models\SinglePageDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResolveControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_single_page_by_path(): void
    {
        $singlePage = SinglePage::factory()->create(['parent_path' => 'company', 'slug' => 'about']);
        SinglePageDetail::factory()->create(['single_page_id' => $singlePage->id, 'sub_title' => '事業内容', 'sort_order' => 1]);
        SinglePageDetail::factory()->create(['single_page_id' => $singlePage->id, 'sub_title' => '沿革', 'sort_order' => 0]);

        $response = $this->getJson(route('api.resolve', ['path' => '/company/about']));

        $response->assertOk();
        $response->assertJsonPath('type', 'single_page');
        $response->assertJsonPath('data.id', $singlePage->id);
        $response->assertJsonPath('data.path', '/company/about');
        $response->assertJsonPath('data.details.0.sub_title', '沿革');
        $response->assertJsonPath('data.details.1.sub_title', '事業内容');
    }

    public function test_resolves_single_page_at_site_root_and_ignores_surrounding_slashes(): void
    {
        $singlePage = SinglePage::factory()->create(['parent_path' => null, 'slug' => 'about']);

        $response = $this->getJson(route('api.resolve', ['path' => 'about/']));

        $response->assertOk();
        $response->assertJsonPath('data.id', $singlePage->id);
        $response->assertJsonPath('data.path', '/about');
    }

    public function test_resolves_published_article_by_slug(): void
    {
        $article = Article::factory()->published()->create(['parent_path' => 'news', 'slug' => 'first-post']);

        $response = $this->getJson(route('api.resolve', ['path' => '/news/first-post']));

        $response->assertOk();
        $response->assertJsonPath('type', 'article');
        $response->assertJsonPath('data.id', $article->id);
        $response->assertJsonPath('data.path', '/news/first-post');
    }

    public function test_resolves_published_article_by_id_when_slug_is_empty(): void
    {
        $article = Article::factory()->published()->create(['parent_path' => 'news', 'slug' => null]);

        $response = $this->getJson(route('api.resolve', ['path' => '/news/'.$article->id]));

        $response->assertOk();
        $response->assertJsonPath('type', 'article');
        $response->assertJsonPath('data.id', $article->id);
        $response->assertJsonPath('data.path', '/news/'.$article->id);
    }

    public function test_returns_404_for_unpublished_article(): void
    {
        Article::factory()->create(['parent_path' => 'news', 'slug' => 'draft-post', 'approval' => ArticleApprovalStatus::Draft]);

        $this->getJson(route('api.resolve', ['path' => '/news/draft-post']))->assertNotFound();
    }

    public function test_returns_404_for_unknown_or_soft_deleted_path(): void
    {
        SinglePage::factory()->create(['slug' => 'deleted'])->delete();
        Article::factory()->published()->create(['slug' => 'deleted-post'])->delete();

        $this->getJson(route('api.resolve', ['path' => '/unknown']))->assertNotFound();
        $this->getJson(route('api.resolve', ['path' => '/deleted']))->assertNotFound();
        $this->getJson(route('api.resolve', ['path' => '/deleted-post']))->assertNotFound();
    }

    /**
     * @return array<string, array{string|null, string|null, bool}>
     */
    public static function publicationPeriodProvider(): array
    {
        return [
            '公開開始前' => ['2026-10-01 10:01:00', null, false],
            '公開開始ちょうど' => ['2026-10-01 10:00:00', null, true],
            '公開終了なしで公開中' => ['2026-09-01 00:00:00', null, true],
            '公開終了前' => ['2026-09-01 00:00:00', '2026-10-01 10:01:00', true],
            '公開終了ちょうど' => ['2026-09-01 00:00:00', '2026-10-01 10:00:00', false],
            '公開終了後' => ['2026-09-01 00:00:00', '2026-09-30 23:59:00', false],
        ];
    }

    #[DataProvider('publicationPeriodProvider')]
    public function test_excludes_single_page_outside_publication_period(string $start, ?string $end, bool $visible): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        SinglePage::factory()->create([
            'slug' => 'campaign',
            'publication_start_datetime' => $start,
            'publication_end_datetime' => $end,
        ]);

        $response = $this->getJson(route('api.resolve', ['path' => '/campaign']));

        $response->assertStatus($visible ? 200 : 404);
    }

    #[DataProvider('publicationPeriodProvider')]
    public function test_excludes_article_outside_publication_period(string $start, ?string $end, bool $visible): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        Article::factory()->published()->create([
            'parent_path' => 'news',
            'slug' => 'campaign',
            'publication_start_datetime' => $start,
            'publication_end_datetime' => $end,
        ]);

        $response = $this->getJson(route('api.resolve', ['path' => '/news/campaign']));

        $response->assertStatus($visible ? 200 : 404);
    }

    public function test_top_path_returns_top_call_contents_in_sort_order(): void
    {
        $article = Article::factory()->published()->create();
        $this->callContent('2番目', CallType::Link, 'Article', CallContentPlace::Top, 1);
        $this->callContent('1番目', CallType::Archive, 'Article', CallContentPlace::Top, 0);
        $this->callContent('その他', CallType::LinkList, 'Article', CallContentPlace::Others, 0);

        $response = $this->getJson(route('api.resolve', ['path' => '/']));

        $response->assertOk();
        $response->assertJsonPath('type', 'top');
        $response->assertJsonPath('data', null);
        $this->assertSame(['1番目', '2番目'], array_column($response->json('call_contents'), 'call_name'));
        $response->assertJsonPath('call_contents.0.call_type', 'archive');
        $response->assertJsonPath('call_contents.1.articles.id', $article->id);
    }

    public function test_single_page_fills_its_original_text_slot_and_omits_article_body_slot(): void
    {
        $singlePage = SinglePage::factory()->create(['slug' => 'about']);
        SinglePage::factory()->create(['slug' => 'other', 'sort_order' => -1]);
        $this->callContent('記事本文', CallType::OriginalText, 'Article', CallContentPlace::Inside, 0);
        $this->callContent('固定ページ本文', CallType::OriginalText, 'SinglePage', CallContentPlace::Inside, 1);
        $this->callContent('トップ', CallType::Link, 'SinglePage', CallContentPlace::Top, 0);

        $response = $this->getJson(route('api.resolve', ['path' => '/about']));

        $response->assertOk();
        $response->assertJsonPath('type', 'single_page');
        $response->assertJsonCount(1, 'call_contents');
        $response->assertJsonPath('call_contents.0.call_name', '固定ページ本文');
        $response->assertJsonPath('call_contents.0.call_type', 'original_text');
        $response->assertJsonPath('call_contents.0.single_pages.id', $singlePage->id);
    }

    public function test_article_fills_its_original_text_slot_instead_of_latest_article(): void
    {
        $article = Article::factory()->published()->create(['parent_path' => 'news', 'slug' => 'old-post']);
        Article::factory()->published()->create(['parent_path' => 'news', 'slug' => 'latest-post']);
        $this->callContent('固定ページ本文', CallType::OriginalText, 'SinglePage', CallContentPlace::Inside, 0);
        $this->callContent('記事本文', CallType::OriginalText, 'Article', CallContentPlace::Inside, 1);

        $response = $this->getJson(route('api.resolve', ['path' => '/news/old-post']));

        $response->assertOk();
        $response->assertJsonPath('type', 'article');
        $response->assertJsonCount(1, 'call_contents');
        $response->assertJsonPath('call_contents.0.call_name', '記事本文');
        $response->assertJsonPath('call_contents.0.articles.id', $article->id);
    }

    public function test_requires_path(): void
    {
        $this->getJson(route('api.resolve'))->assertUnprocessable()->assertJsonValidationErrors('path');
    }

    /**
     * 呼び出しコンテンツを作成する(データ種別の紐付けはモデル名ごとに1件を使い回す)。
     */
    private function callContent(string $callName, CallType $callType, string $modelName, CallContentPlace $place, int $sortOrder): CallContent
    {
        $relation = ContentModelRelation::query()->firstOrCreate(
            ['model_name' => $modelName],
            [
                'content_type' => $modelName === 'Article' ? CallContentType::Article : CallContentType::SinglePage,
                'table_name' => $modelName === 'Article' ? 'articles' : 'single_pages',
            ]
        );

        return CallContent::factory()->create([
            'call_name' => $callName,
            'call_type' => $callType,
            'content_model_relation_id' => $relation->id,
            'place' => $place,
            'sort_order' => $sortOrder,
        ]);
    }
}
