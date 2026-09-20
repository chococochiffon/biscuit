<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_tag_pages(): void
    {
        $response = $this->get(route('admin.tags.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_search_returns_matching_tags(): void
    {
        $actor = Administrator::factory()->create();
        Tag::factory()->create(['tag_name' => 'Laravel']);
        Tag::factory()->create(['tag_name' => 'PHP']);

        $response = $this->actingAs($actor, 'admin')->getJson(route('admin.tags.search', ['q' => 'lara']));

        $response->assertOk();
        $response->assertJsonFragment(['tag_name' => 'Laravel']);
        $response->assertJsonMissing(['tag_name' => 'PHP']);
    }

    public function test_search_without_keyword_returns_all_tags(): void
    {
        $actor = Administrator::factory()->create();
        Tag::factory()->create(['tag_name' => 'Laravel']);

        $response = $this->actingAs($actor, 'admin')->getJson(route('admin.tags.search'));

        $response->assertOk();
        $response->assertJsonFragment(['tag_name' => 'Laravel']);
    }

    public function test_index_displays_tags(): void
    {
        $actor = Administrator::factory()->create();
        $tag = Tag::factory()->create(['tag_name' => 'Laravel']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.tags.index'));

        $response->assertOk();
        $response->assertSee($tag->tag_name);
    }

    public function test_create_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.tags.create'));

        $response->assertOk();
    }

    public function test_store_creates_tag_with_valid_data(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.tags.store'), [
            'tag_name' => 'PHP',
        ]);

        $response->assertRedirect(route('admin.tags.index'));
        $this->assertDatabaseHas('tags', ['tag_name' => 'PHP']);
    }

    public function test_store_fails_validation_with_missing_fields(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.tags.store'), []);

        $response->assertSessionHasErrors(['tag_name']);
    }

    public function test_store_fails_when_tag_name_already_exists(): void
    {
        $actor = Administrator::factory()->create();
        $existing = Tag::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.tags.store'), [
            'tag_name' => $existing->tag_name,
        ]);

        $response->assertSessionHasErrors('tag_name');
    }

    public function test_store_succeeds_with_tag_name_of_soft_deleted_tag(): void
    {
        $actor = Administrator::factory()->create();
        $deleted = Tag::factory()->create(['tag_name' => 'reused-tag']);
        $deleted->delete();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.tags.store'), [
            'tag_name' => 'reused-tag',
        ]);

        $response->assertRedirect(route('admin.tags.index'));
        $this->assertDatabaseHas('tags', [
            'tag_name' => 'reused-tag',
            'deleted_at' => null,
        ]);
    }

    public function test_show_displays_tag(): void
    {
        $actor = Administrator::factory()->create();
        $target = Tag::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.tags.show', $target));

        $response->assertOk();
        $response->assertSee($target->tag_name);
    }

    public function test_edit_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();
        $target = Tag::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.tags.edit', $target));

        $response->assertOk();
    }

    public function test_update_modifies_tag(): void
    {
        $actor = Administrator::factory()->create();
        $target = Tag::factory()->create();

        $response = $this->actingAs($actor, 'admin')->put(route('admin.tags.update', $target), [
            'tag_name' => 'Updated Tag',
        ]);

        $response->assertRedirect(route('admin.tags.index'));
        $this->assertSame('Updated Tag', $target->fresh()->tag_name);
    }

    public function test_update_fails_when_tag_name_belongs_to_another_tag(): void
    {
        $actor = Administrator::factory()->create();
        $target = Tag::factory()->create();
        $other = Tag::factory()->create();

        $response = $this->actingAs($actor, 'admin')->put(route('admin.tags.update', $target), [
            'tag_name' => $other->tag_name,
        ]);

        $response->assertSessionHasErrors('tag_name');
    }

    public function test_destroy_deletes_tag(): void
    {
        $actor = Administrator::factory()->create();
        $target = Tag::factory()->create();

        $response = $this->actingAs($actor, 'admin')->delete(route('admin.tags.destroy', $target));

        $response->assertRedirect(route('admin.tags.index'));
        $this->assertSoftDeleted('tags', ['id' => $target->id]);
    }
}
