<?php

namespace Tests\Feature;

use App\Enums\CallContentType;
use App\Models\Administrator;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContentModelRelationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_content_model_relation_pages(): void
    {
        $response = $this->get(route('admin.content-model-relations.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_index_displays_content_model_relations(): void
    {
        $actor = Administrator::factory()->create();
        $target = ContentModelRelation::factory()->create(['model_name' => 'article']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.content-model-relations.index'));

        $response->assertOk();
        $response->assertSee($target->model_name);
    }

    public function test_create_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.content-model-relations.create'));

        $response->assertOk();
    }

    public function test_create_screen_offers_table_names_from_the_database_as_a_select(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.content-model-relations.create'));

        $response->assertOk();
        $response->assertSee('<select id="table_name" name="table_name"', false);
        $response->assertSee('<option value="articles"', false);
        $response->assertSee('<option value="single_pages"', false);
        $response->assertDontSee('<option value="users"', false);
    }

    public function test_create_screen_offers_user_details_even_though_the_table_does_not_exist_yet(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.content-model-relations.create'));

        $response->assertOk();
        $response->assertSee('<option value="user_details"', false);
    }

    public function test_create_screen_offers_user_made_tables_that_exist_in_the_database(): void
    {
        Schema::create('user_make_recipes', fn (Blueprint $table) => $table->id());
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.content-model-relations.create'));

        $response->assertOk();
        $response->assertSee('<option value="user_make_recipes"', false);

        Schema::dropIfExists('user_make_recipes');
    }

    public function test_edit_screen_keeps_the_current_table_name_as_an_option_even_if_not_a_real_table(): void
    {
        $actor = Administrator::factory()->create();
        $target = ContentModelRelation::factory()->create(['table_name' => 'user_make_recipes']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.content-model-relations.edit', $target));

        $response->assertOk();
        $response->assertSee('<option value="user_make_recipes"', false);
    }

    public function test_store_creates_content_model_relation_with_valid_data(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.content-model-relations.store'), [
            'content_type' => CallContentType::Article->value,
            'model_name' => 'article',
            'table_name' => 'articles',
        ]);

        $response->assertRedirect(route('admin.content-model-relations.index'));
        $this->assertDatabaseHas('content_model_relations', [
            'content_type' => CallContentType::Article->value,
            'model_name' => 'article',
            'table_name' => 'articles',
        ]);
    }

    public function test_store_allows_user_made_table_name(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.content-model-relations.store'), [
            'content_type' => CallContentType::Custom->value,
            'model_name' => 'user_made_list',
            'table_name' => 'user_make_recipes',
        ]);

        $response->assertRedirect(route('admin.content-model-relations.index'));
        $this->assertDatabaseHas('content_model_relations', ['table_name' => 'user_make_recipes']);
    }

    public function test_store_fails_validation_with_missing_fields(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.content-model-relations.store'), []);

        $response->assertSessionHasErrors(['content_type', 'model_name', 'table_name']);
    }

    public function test_store_fails_with_disallowed_table_name(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.content-model-relations.store'), [
            'content_type' => CallContentType::Article->value,
            'model_name' => 'article',
            'table_name' => 'unrelated_table',
        ]);

        $response->assertSessionHasErrors('table_name');
    }

    public function test_store_fails_when_content_type_and_model_name_pair_already_exists(): void
    {
        $actor = Administrator::factory()->create();
        $existing = ContentModelRelation::factory()->create([
            'content_type' => CallContentType::Article,
            'model_name' => 'article',
        ]);

        $response = $this->actingAs($actor, 'admin')->post(route('admin.content-model-relations.store'), [
            'content_type' => $existing->content_type->value,
            'model_name' => $existing->model_name,
            'table_name' => 'articles',
        ]);

        $response->assertSessionHasErrors('model_name');
    }

    public function test_show_displays_content_model_relation(): void
    {
        $actor = Administrator::factory()->create();
        $target = ContentModelRelation::factory()->create(['model_name' => 'article']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.content-model-relations.show', $target));

        $response->assertOk();
        $response->assertSee($target->model_name);
    }

    public function test_edit_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();
        $target = ContentModelRelation::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.content-model-relations.edit', $target));

        $response->assertOk();
    }

    public function test_update_modifies_content_model_relation(): void
    {
        $actor = Administrator::factory()->create();
        $target = ContentModelRelation::factory()->create([
            'content_type' => CallContentType::Article,
            'model_name' => 'article',
            'table_name' => 'articles',
        ]);

        $response = $this->actingAs($actor, 'admin')->put(route('admin.content-model-relations.update', $target), [
            'content_type' => CallContentType::SinglePage->value,
            'model_name' => 'single_page',
            'table_name' => 'single_pages',
        ]);

        $response->assertRedirect(route('admin.content-model-relations.index'));
        $fresh = $target->fresh();
        $this->assertSame(CallContentType::SinglePage, $fresh->content_type);
        $this->assertSame('single_page', $fresh->model_name);
        $this->assertSame('single_pages', $fresh->table_name);
    }

    public function test_update_fails_with_disallowed_table_name(): void
    {
        $actor = Administrator::factory()->create();
        $target = ContentModelRelation::factory()->create();

        $response = $this->actingAs($actor, 'admin')->put(route('admin.content-model-relations.update', $target), [
            'content_type' => $target->content_type->value,
            'model_name' => $target->model_name,
            'table_name' => 'unrelated_table',
        ]);

        $response->assertSessionHasErrors('table_name');
    }

    public function test_destroy_deletes_content_model_relation_when_not_used_by_call_content(): void
    {
        $actor = Administrator::factory()->create();
        $target = ContentModelRelation::factory()->create([
            'content_type' => CallContentType::Article,
            'model_name' => 'article',
        ]);

        $response = $this->actingAs($actor, 'admin')->delete(route('admin.content-model-relations.destroy', $target));

        $response->assertRedirect(route('admin.content-model-relations.index'));
        $this->assertSoftDeleted('content_model_relations', ['id' => $target->id]);
    }

    public function test_destroy_is_blocked_when_used_by_call_content(): void
    {
        $actor = Administrator::factory()->create();
        $target = ContentModelRelation::factory()->create();
        CallContent::factory()->create(['content_model_relation_id' => $target->id]);

        $response = $this->actingAs($actor, 'admin')->delete(route('admin.content-model-relations.destroy', $target));

        $response->assertRedirect(route('admin.content-model-relations.index'));
        $this->assertDatabaseHas('content_model_relations', ['id' => $target->id, 'deleted_at' => null]);
    }
}
