<?php

namespace Tests\Feature;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 記事一覧のタグ管理モーダルと記事編集のタグ選択が使うタグの JSON(TagJsonController)。
 */
class TagJsonControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_tag_json(): void
    {
        $this->getJson(route('admin.json.tags.index'))->assertUnauthorized();
    }

    public function test_search_returns_matching_tags(): void
    {
        $this->actingAsAdmin();
        Tag::factory()->create(['tag_name' => 'Laravel']);
        Tag::factory()->create(['tag_name' => 'PHP']);

        $response = $this->getJson(route('admin.json.tags.search', ['q' => 'lara']));

        $response->assertOk();
        $response->assertJsonFragment(['tag_name' => 'Laravel']);
        $response->assertJsonMissing(['tag_name' => 'PHP']);
    }

    public function test_search_without_keyword_returns_all_tags(): void
    {
        $this->actingAsAdmin();
        Tag::factory()->create(['tag_name' => 'Laravel']);

        $response = $this->getJson(route('admin.json.tags.search'));

        $response->assertOk();
        $response->assertJsonFragment(['tag_name' => 'Laravel']);
    }

    public function test_index_returns_all_tags(): void
    {
        $this->actingAsAdmin();
        $tag = Tag::factory()->create(['tag_name' => 'Laravel']);

        $response = $this->getJson(route('admin.json.tags.index'));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $tag->id, 'tag_name' => 'Laravel']);
    }

    public function test_store_creates_tag_and_returns_it(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.json.tags.store'), [
            'tag_name' => 'PHP',
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['tag_name' => 'PHP']);
        $this->assertDatabaseHas('tags', ['tag_name' => 'PHP']);
    }

    public function test_store_fails_validation_with_missing_fields(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(route('admin.json.tags.store'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['tag_name']);
    }

    public function test_update_modifies_tag_and_returns_it(): void
    {
        $this->actingAsAdmin();
        $target = Tag::factory()->create();

        $response = $this->putJson(route('admin.json.tags.update', $target), [
            'tag_name' => 'Updated Tag',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['tag_name' => 'Updated Tag']);
        $this->assertSame('Updated Tag', $target->fresh()->tag_name);
    }

    public function test_destroy_deletes_tag(): void
    {
        $this->actingAsAdmin();
        $target = Tag::factory()->create();

        $response = $this->deleteJson(route('admin.json.tags.destroy', $target));

        $response->assertNoContent();
        $this->assertSoftDeleted('tags', ['id' => $target->id]);
    }

    public function test_store_and_update_return_only_id_and_tag_name(): void
    {
        $this->actingAsAdmin();

        $created = $this->postJson(route('admin.json.tags.store'), ['tag_name' => 'PHP'])->assertCreated();
        $this->assertSame(['id', 'tag_name'], array_keys($created->json()));

        $updated = $this->putJson(route('admin.json.tags.update', $created->json('id')), ['tag_name' => 'Laravel'])->assertOk();
        $this->assertSame(['id' => $created->json('id'), 'tag_name' => 'Laravel'], $updated->json());
    }

    public function test_update_fails_validation_for_a_duplicate_name(): void
    {
        $this->actingAsAdmin();
        Tag::factory()->create(['tag_name' => 'PHP']);
        $target = Tag::factory()->create(['tag_name' => 'Laravel']);

        $this->putJson(route('admin.json.tags.update', $target), ['tag_name' => 'PHP'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tag_name']);
    }
}
