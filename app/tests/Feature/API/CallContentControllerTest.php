<?php

namespace Tests\Feature\API;

use App\Enums\CallContentPlace;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallContentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_defaults_to_top_place_when_place_is_not_specified(): void
    {
        $top = CallContent::factory()->create(['place' => CallContentPlace::Top]);
        CallContent::factory()->create(['place' => CallContentPlace::Inside]);

        $response = $this->getJson(route('call-contents.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $top->id);
    }

    public function test_index_filters_by_specified_place(): void
    {
        CallContent::factory()->create(['place' => CallContentPlace::Top]);
        $inside = CallContent::factory()->create(['place' => CallContentPlace::Inside]);

        $response = $this->getJson(route('call-contents.index', ['place' => CallContentPlace::Inside->value]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $inside->id);
    }

    public function test_index_returns_422_for_invalid_place(): void
    {
        $response = $this->getJson(route('call-contents.index', ['place' => 999]));

        $response->assertStatus(422);
    }

    public function test_index_includes_content_model_relation(): void
    {
        $relation = ContentModelRelation::factory()->create([
            'model_name' => 'Article',
            'table_name' => 'articles',
        ]);
        CallContent::factory()->create([
            'place' => CallContentPlace::Top,
            'content_model_relation_id' => $relation->id,
        ]);

        $response = $this->getJson(route('call-contents.index'));

        $response->assertOk();
        $response->assertJsonPath('data.0.content_model_relation.id', $relation->id);
        $response->assertJsonPath('data.0.content_model_relation.model_name', 'Article');
        $response->assertJsonPath('data.0.content_model_relation.table_name', 'articles');
    }
}
