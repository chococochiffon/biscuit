<?php

namespace Tests\Feature;

use App\Enums\CallContentType;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use App\Models\LayoutBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * サイト設定画面のデータ種別紐付け管理モーダルが使うデータ種別紐付けの JSON(ContentModelRelationJsonController)。
 */
class ContentModelRelationJsonControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_content_model_relation_json(): void
    {
        $this->getJson(route('admin.json.content-model-relations.index'))->assertUnauthorized();
    }

    public function test_index_returns_relations_ordered_by_content_type_and_model_name(): void
    {
        $this->actingAsAdmin();
        $second = ContentModelRelation::factory()->create(['content_type' => CallContentType::Article, 'model_name' => 'b', 'table_name' => 'articles']);
        $first = ContentModelRelation::factory()->create(['content_type' => CallContentType::Article, 'model_name' => 'a', 'table_name' => 'articles']);

        $response = $this->getJson(route('admin.json.content-model-relations.index'));

        $response->assertOk();
        $this->assertSame([$first->id, $second->id], array_column($response->json(), 'id'));
        $response->assertJsonPath('0', [
            'id' => $first->id,
            'content_type' => CallContentType::Article->value,
            'content_type_label' => CallContentType::Article->label(),
            'model_name' => 'a',
            'table_name' => 'articles',
            'matrix_model_name' => $first->matrixModelName(),
        ]);
    }

    public function test_store_creates_relation_and_returns_it(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.json.content-model-relations.store'), [
            'content_type' => CallContentType::Article->value,
            'model_name' => 'article',
            'table_name' => 'articles',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('model_name', 'article');
        $response->assertJsonPath('content_type_label', CallContentType::Article->label());
        $this->assertDatabaseHas('content_model_relations', ['model_name' => 'article', 'table_name' => 'articles']);
    }

    public function test_store_fails_validation_with_missing_fields(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.json.content-model-relations.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content_type', 'model_name', 'table_name']);
    }

    public function test_update_modifies_relation_and_returns_it(): void
    {
        $this->actingAsAdmin();
        $target = ContentModelRelation::factory()->create(['content_type' => CallContentType::Article, 'model_name' => 'article', 'table_name' => 'articles']);

        $response = $this->putJson(route('admin.json.content-model-relations.update', $target), [
            'content_type' => CallContentType::Article->value,
            'model_name' => 'news',
            'table_name' => 'articles',
        ]);

        $response->assertOk();
        $response->assertJsonPath('id', $target->id);
        $response->assertJsonPath('model_name', 'news');
        $this->assertSame('news', $target->fresh()->model_name);
    }

    public function test_destroy_deletes_relation(): void
    {
        $this->actingAsAdmin();
        $target = ContentModelRelation::factory()->create();

        $this->deleteJson(route('admin.json.content-model-relations.destroy', $target))->assertNoContent();

        $this->assertSoftDeleted('content_model_relations', ['id' => $target->id]);
    }

    public function test_destroy_is_rejected_when_used_by_call_contents(): void
    {
        $this->actingAsAdmin();
        $target = ContentModelRelation::factory()->create();
        CallContent::factory()->create(['content_model_relation_id' => $target->id]);

        $this->deleteJson(route('admin.json.content-model-relations.destroy', $target))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'このデータ種別の紐付けはcall_contentsで使用されているため削除できません。');

        $this->assertNotSoftDeleted($target);
    }

    public function test_destroy_is_rejected_when_used_by_layout_blocks(): void
    {
        $this->actingAsAdmin();
        $target = ContentModelRelation::factory()->create();
        LayoutBlock::factory()->callContent(relation: $target)->create();

        $this->deleteJson(route('admin.json.content-model-relations.destroy', $target))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'このデータ種別の紐付けはレイアウトの部品で使用されているため削除できません。');

        $this->assertNotSoftDeleted($target);
    }

    public function test_blocked_destroy_message_is_shown_in_the_selected_language(): void
    {
        $this->actingAsAdmin();
        $target = ContentModelRelation::factory()->create();
        CallContent::factory()->create(['content_model_relation_id' => $target->id]);

        $this->withSession(['locale' => 'en'])
            ->deleteJson(route('admin.json.content-model-relations.destroy', $target))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This data type mapping cannot be deleted because it is used by call contents.');
    }
}
