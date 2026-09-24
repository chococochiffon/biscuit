<?php

namespace Tests\Feature\API;

use App\Enums\CallContentPlace;
use App\Enums\CallContentType;
use App\Enums\CallType;
use App\Models\Article;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use App\Models\SinglePage;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallContentControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, string>
     */
    private const TABLE_NAMES = [
        'Article' => 'articles',
        'SinglePage' => 'single_pages',
        'UserDetail' => 'user_details',
    ];

    private function relation(string $modelName): ContentModelRelation
    {
        return ContentModelRelation::query()->firstOrCreate(
            ['content_type' => CallContentType::Article, 'model_name' => $modelName],
            ['table_name' => self::TABLE_NAMES[$modelName]]
        );
    }

    public function test_index_defaults_to_top_place_when_place_is_not_specified(): void
    {
        $latest = Article::factory()->published()->create();
        CallContent::factory()->create([
            'call_type' => CallType::Link,
            'place' => CallContentPlace::Top,
            'content_model_relation_id' => $this->relation('Article')->id,
        ]);
        CallContent::factory()->create([
            'call_type' => CallType::Link,
            'place' => CallContentPlace::Inside,
            'content_model_relation_id' => $this->relation('Article')->id,
        ]);

        $response = $this->getJson(route('call-contents.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.articles.id', $latest->id);
    }

    public function test_index_filters_by_specified_place(): void
    {
        CallContent::factory()->create([
            'call_type' => CallType::Link,
            'place' => CallContentPlace::Top,
            'content_model_relation_id' => $this->relation('Article')->id,
        ]);
        $insideArticle = Article::factory()->published()->create();
        CallContent::factory()->create([
            'call_type' => CallType::OriginalText,
            'place' => CallContentPlace::Inside,
            'content_model_relation_id' => $this->relation('Article')->id,
        ]);

        $response = $this->getJson(route('call-contents.index', ['place' => CallContentPlace::Inside->value]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.articles.id', $insideArticle->id);
    }

    public function test_index_returns_422_for_invalid_place(): void
    {
        $response = $this->getJson(route('call-contents.index', ['place' => 999]));

        $response->assertStatus(422);
    }

    public function test_index_item_contains_call_type_call_name_and_table_name_keyed_data(): void
    {
        $article = Article::factory()->published()->create();
        CallContent::factory()->create([
            'call_type' => CallType::Link,
            'call_name' => '注目記事',
            'title' => 'Pickup',
            'subtitle' => 'おすすめの記事',
            'place' => CallContentPlace::Top,
            'content_model_relation_id' => $this->relation('Article')->id,
        ]);

        $response = $this->getJson(route('call-contents.index'));

        $response->assertOk();
        $this->assertSame(['call_type', 'call_name', 'title', 'subtitle', 'articles'], array_keys($response->json('data.0')));
        $response->assertJsonPath('data.0.call_type', 'link');
        $response->assertJsonPath('data.0.call_name', '注目記事');
        $response->assertJsonPath('data.0.title', 'Pickup');
        $response->assertJsonPath('data.0.subtitle', 'おすすめの記事');
        $response->assertJsonPath('data.0.articles.id', $article->id);
    }

    public function test_index_returns_null_title_and_subtitle_when_not_set(): void
    {
        Article::factory()->published()->create();
        CallContent::factory()->create([
            'call_type' => CallType::Link,
            'place' => CallContentPlace::Top,
            'content_model_relation_id' => $this->relation('Article')->id,
        ]);

        $response = $this->getJson(route('call-contents.index'));

        $response->assertOk();
        $response->assertJsonPath('data.0.title', null);
        $response->assertJsonPath('data.0.subtitle', null);
    }

    public function test_index_orders_items_by_sort_order(): void
    {
        foreach (['2番目' => 1, '3番目' => 2, '1番目' => 0] as $callName => $sortOrder) {
            CallContent::factory()->create([
                'call_type' => CallType::Link,
                'call_name' => $callName,
                'place' => CallContentPlace::Top,
                'sort_order' => $sortOrder,
                'content_model_relation_id' => $this->relation('Article')->id,
            ]);
        }

        $response = $this->getJson(route('call-contents.index'));

        $response->assertOk();
        $this->assertSame(['1番目', '2番目', '3番目'], array_column($response->json('data'), 'call_name'));
    }

    public function test_index_resolves_article_link_list_up_to_view_count(): void
    {
        Article::factory()->published()->count(3)->create();
        CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'place' => CallContentPlace::Others,
            'view_count' => 2,
            'content_model_relation_id' => $this->relation('Article')->id,
        ]);

        $response = $this->getJson(route('call-contents.index', ['place' => CallContentPlace::Others->value]));

        $response->assertOk();
        $response->assertJsonCount(2, 'data.0.articles');
    }

    public function test_index_resolves_single_page_link_list_filtered_by_top_page_view(): void
    {
        SinglePage::factory()->create(['top_page_view' => false]);
        $visible = SinglePage::factory()->create(['top_page_view' => true]);
        CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'place' => CallContentPlace::Top,
            'content_model_relation_id' => $this->relation('SinglePage')->id,
        ]);

        $response = $this->getJson(route('call-contents.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data.0.single_pages');
        $response->assertJsonPath('data.0.single_pages.0.id', $visible->id);
    }

    public function test_index_excludes_articles_outside_publication_period(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        Article::factory()->published()->create(['publication_start_datetime' => '2026-10-02 00:00:00']);
        Article::factory()->published()->create(['publication_start_datetime' => '2026-09-01 00:00:00', 'publication_end_datetime' => '2026-09-30 00:00:00']);
        $visible = Article::factory()->published()->create(['publication_start_datetime' => '2026-09-01 00:00:00', 'publication_end_datetime' => null]);
        CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'place' => CallContentPlace::Others,
            'view_count' => 10,
            'content_model_relation_id' => $this->relation('Article')->id,
        ]);

        $response = $this->getJson(route('call-contents.index', ['place' => CallContentPlace::Others->value]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data.0.articles');
        $response->assertJsonPath('data.0.articles.0.id', $visible->id);
    }

    public function test_index_excludes_single_pages_outside_publication_period(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        SinglePage::factory()->create(['top_page_view' => true, 'sort_order' => 0, 'publication_start_datetime' => '2026-10-02 00:00:00']);
        SinglePage::factory()->create(['top_page_view' => true, 'sort_order' => 1, 'publication_start_datetime' => '2026-09-01 00:00:00', 'publication_end_datetime' => '2026-09-30 00:00:00']);
        $visible = SinglePage::factory()->create(['top_page_view' => true, 'sort_order' => 2, 'publication_start_datetime' => '2026-09-01 00:00:00']);
        CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'place' => CallContentPlace::Top,
            'content_model_relation_id' => $this->relation('SinglePage')->id,
        ]);

        $response = $this->getJson(route('call-contents.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data.0.single_pages');
        $response->assertJsonPath('data.0.single_pages.0.id', $visible->id);
    }

    public function test_index_resolves_user_detail_skill_list_filtered_by_view_flag(): void
    {
        UserDetail::factory()->create(['view_flag' => false]);
        $visible = UserDetail::factory()->create(['view_flag' => true]);
        CallContent::factory()->create([
            'call_type' => CallType::SkillList,
            'place' => CallContentPlace::Top,
            'view_count' => 5,
            'content_model_relation_id' => $this->relation('UserDetail')->id,
        ]);

        $response = $this->getJson(route('call-contents.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data.0.user_details');
        $response->assertJsonPath('data.0.user_details.0.id', $visible->id);
    }

    public function test_index_fails_when_the_stored_combination_has_no_defined_rule(): void
    {
        // OriginalTextはOthers(その他)では、どのモデルに対しても許可されていない組み合わせ。
        CallContent::factory()->create([
            'call_type' => CallType::OriginalText,
            'place' => CallContentPlace::Others,
            'content_model_relation_id' => $this->relation('Article')->id,
        ]);

        $response = $this->getJson(route('call-contents.index', ['place' => CallContentPlace::Others->value]));

        $response->assertStatus(500);
    }

    public function test_index_fails_when_model_name_is_unrecognized_at_top_place(): void
    {
        $relation = ContentModelRelation::factory()->create([
            'content_type' => CallContentType::Article,
            'model_name' => 'Recipe',
        ]);
        CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'place' => CallContentPlace::Top,
            'content_model_relation_id' => $relation->id,
        ]);

        $response = $this->getJson(route('call-contents.index'));

        $response->assertStatus(500);
    }
}
