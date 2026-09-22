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
        CallContent::factory()->create([
            'call_type' => CallType::Link,
            'place' => CallContentPlace::Inside,
            'content_model_relation_id' => $this->relation('Article')->id,
        ]);

        $response = $this->getJson(route('call-contents.index', ['place' => CallContentPlace::Inside->value]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.articles', null);
    }

    public function test_index_returns_422_for_invalid_place(): void
    {
        $response = $this->getJson(route('call-contents.index', ['place' => 999]));

        $response->assertStatus(422);
    }

    public function test_index_item_only_contains_the_table_name_keyed_field(): void
    {
        Article::factory()->published()->create();
        CallContent::factory()->create([
            'call_type' => CallType::Link,
            'place' => CallContentPlace::Top,
            'content_model_relation_id' => $this->relation('Article')->id,
        ]);

        $response = $this->getJson(route('call-contents.index'));

        $response->assertOk();
        $this->assertSame(['articles'], array_keys($response->json('data.0')));
    }

    public function test_index_resolves_article_link_list_up_to_view_count(): void
    {
        Article::factory()->published()->count(3)->create();
        CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'place' => CallContentPlace::Top,
            'view_count' => 2,
            'content_model_relation_id' => $this->relation('Article')->id,
        ]);

        $response = $this->getJson(route('call-contents.index'));

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

    public function test_index_returns_null_for_a_place_without_defined_rules(): void
    {
        CallContent::factory()->create([
            'call_type' => CallType::LinkList,
            'place' => CallContentPlace::Others,
            'content_model_relation_id' => $this->relation('Article')->id,
        ]);

        $response = $this->getJson(route('call-contents.index', ['place' => CallContentPlace::Others->value]));

        $response->assertOk();
        $response->assertJsonPath('data.0.articles', null);
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
