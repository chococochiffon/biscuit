<?php

namespace Tests\Feature;

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

    public function test_index_displays_tags(): void
    {
        $this->actingAsAdmin();
        $tag = Tag::factory()->create(['tag_name' => 'Laravel']);

        $response = $this->get(route('admin.tags.index'));

        $response->assertOk();
        $response->assertSee($tag->tag_name);
    }

    public function test_index_shows_delete_button_for_each_tag_and_empty_row_without_tags(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.tags.index'))
            ->assertOk()
            ->assertSee('タグが登録されていません。');

        $tag = Tag::factory()->create();

        $this->get(route('admin.tags.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'action="'.route('admin.tags.destroy', $tag).'"',
                'onsubmit="return confirm(',
                'name="_method" value="DELETE"',
            ], false)
            ->assertDontSee('タグが登録されていません。');
    }

    public function test_create_screen_can_be_rendered(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.tags.create'));

        $response->assertOk();
    }

    public function test_store_creates_tag_with_valid_data(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.tags.store'), [
            'tag_name' => 'PHP',
        ]);

        $response->assertRedirect(route('admin.tags.index'));
        $this->assertDatabaseHas('tags', ['tag_name' => 'PHP']);
    }

    public function test_store_fails_validation_with_missing_fields(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.tags.store'), []);

        $response->assertSessionHasErrors(['tag_name']);
    }

    public function test_store_fails_when_tag_name_already_exists(): void
    {
        $this->actingAsAdmin();
        $existing = Tag::factory()->create();

        $response = $this->post(route('admin.tags.store'), [
            'tag_name' => $existing->tag_name,
        ]);

        $response->assertSessionHasErrors('tag_name');
    }

    public function test_create_screen_shows_validation_errors_after_failed_store(): void
    {
        $this->actingAsAdmin();

        $this
            ->from(route('admin.tags.create'))
            ->post(route('admin.tags.store'), [])
            ->assertRedirect(route('admin.tags.create'));

        $this->get(route('admin.tags.create'))
            ->assertOk()
            ->assertSeeInOrder(['<div class="alert alert-danger">', '<li>'], false);
    }

    public function test_store_succeeds_with_tag_name_of_soft_deleted_tag(): void
    {
        $this->actingAsAdmin();
        $deleted = Tag::factory()->create(['tag_name' => 'reused-tag']);
        $deleted->delete();

        $response = $this->post(route('admin.tags.store'), [
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
        $this->actingAsAdmin();
        $target = Tag::factory()->create();

        $response = $this->get(route('admin.tags.show', $target));

        $response->assertOk();
        $response->assertSee($target->tag_name);
    }

    public function test_edit_screen_can_be_rendered(): void
    {
        $this->actingAsAdmin();
        $target = Tag::factory()->create();

        $response = $this->get(route('admin.tags.edit', $target));

        $response->assertOk();
    }

    public function test_update_modifies_tag(): void
    {
        $this->actingAsAdmin();
        $target = Tag::factory()->create();

        $response = $this->put(route('admin.tags.update', $target), [
            'tag_name' => 'Updated Tag',
        ]);

        $response->assertRedirect(route('admin.tags.index'));
        $this->assertSame('Updated Tag', $target->fresh()->tag_name);
    }

    public function test_update_allows_keeping_its_own_tag_name(): void
    {
        $this->actingAsAdmin();
        $target = Tag::factory()->create(['tag_name' => 'そのまま']);

        $this->put(route('admin.tags.update', $target), ['tag_name' => 'そのまま'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.tags.index'));
    }

    public function test_completion_message_is_shown_in_the_selected_language(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.tags.store'), ['tag_name' => '日本語'])
            ->assertSessionHas('status', 'タグを登録しました。');

        $this->withSession(['locale' => 'en'])
            ->post(route('admin.tags.store'), ['tag_name' => 'English'])
            ->assertSessionHas('status', 'The tag has been created.');
    }

    public function test_update_fails_when_tag_name_belongs_to_another_tag(): void
    {
        $this->actingAsAdmin();
        $target = Tag::factory()->create();
        $other = Tag::factory()->create();

        $response = $this->put(route('admin.tags.update', $target), [
            'tag_name' => $other->tag_name,
        ]);

        $response->assertSessionHasErrors('tag_name');
    }

    public function test_destroy_deletes_tag(): void
    {
        $this->actingAsAdmin();
        $target = Tag::factory()->create();

        $response = $this->delete(route('admin.tags.destroy', $target));

        $response->assertRedirect(route('admin.tags.index'));
        $this->assertSoftDeleted('tags', ['id' => $target->id]);
    }
}
