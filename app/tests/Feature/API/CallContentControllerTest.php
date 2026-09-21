<?php

namespace Tests\Feature\API;

use App\Enums\CallContentPlace;
use App\Enums\CallContentType;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallContentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_defaults_to_top_place_when_place_is_not_specified(): void
    {
        $topRelation = ContentModelRelation::factory()->create(['model_name' => 'TopArticle']);
        CallContent::factory()->create(['place' => CallContentPlace::Top, 'content_model_relation_id' => $topRelation->id]);
        CallContent::factory()->create(['place' => CallContentPlace::Inside]);

        $response = $this->getJson(route('call-contents.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.model_name', 'TopArticle');
    }

    public function test_index_filters_by_specified_place(): void
    {
        CallContent::factory()->create(['place' => CallContentPlace::Top]);
        $insideRelation = ContentModelRelation::factory()->create(['model_name' => 'InsidePage']);
        CallContent::factory()->create(['place' => CallContentPlace::Inside, 'content_model_relation_id' => $insideRelation->id]);

        $response = $this->getJson(route('call-contents.index', ['place' => CallContentPlace::Inside->value]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.model_name', 'InsidePage');
    }

    public function test_index_returns_422_for_invalid_place(): void
    {
        $response = $this->getJson(route('call-contents.index', ['place' => 999]));

        $response->assertStatus(422);
    }

    public function test_index_returns_flattened_content_model_relation_fields(): void
    {
        $relation = ContentModelRelation::factory()->create([
            'content_type' => CallContentType::Article,
            'model_name' => 'Article',
            'table_name' => 'articles',
        ]);
        $callContent = CallContent::factory()->create([
            'place' => CallContentPlace::Top,
            'content_model_relation_id' => $relation->id,
        ]);

        $response = $this->getJson(route('call-contents.index'));

        $response->assertOk();
        $response->assertExactJson([
            'data' => [
                [
                    'call_type' => $callContent->call_type->value,
                    'view_count' => $callContent->view_count,
                    'model_name' => 'Article',
                    'table_name' => 'articles',
                    'content_type' => CallContentType::Article->value,
                ],
            ],
        ]);
    }
}
