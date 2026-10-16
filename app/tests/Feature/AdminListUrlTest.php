<?php

namespace Tests\Feature;

use App\Enums\CustomPageBaseType;
use App\Models\Article;
use App\Models\CustomPageType;
use App\Models\Tag;
use App\Support\CustomPages\CustomPageSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminListUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_redirects_to_the_list_page_last_opened_with_its_query(): void
    {
        $this->actingAsAdmin();
        $tag = Tag::factory()->create();
        $listUrl = route('admin.tags.index', ['page' => 2, 'sort' => 'tag_name_asc']);

        $this->get($listUrl)->assertOk();

        $this->put(route('admin.tags.update', $tag), ['tag_name' => '更新後'])
            ->assertRedirect($listUrl);
    }

    public function test_destroy_redirects_to_the_list_page_last_opened_with_its_query(): void
    {
        $this->actingAsAdmin();
        $article = Article::factory()->create();
        $listUrl = route('admin.articles.index', ['page' => 3, 'title' => '検索']);

        $this->get($listUrl)->assertOk();

        $this->delete(route('admin.articles.destroy', $article))
            ->assertRedirect($listUrl);
    }

    public function test_redirects_to_the_list_without_query_when_no_list_was_opened(): void
    {
        $this->actingAsAdmin();
        $tag = Tag::factory()->create();

        $this->put(route('admin.tags.update', $tag), ['tag_name' => '更新後'])
            ->assertRedirect(route('admin.tags.index'));
    }

    public function test_cancel_link_on_edit_screen_points_to_the_list_page_last_opened(): void
    {
        $this->actingAsAdmin();
        $tag = Tag::factory()->create();
        $listUrl = route('admin.tags.index', ['page' => 2]);

        $this->get($listUrl)->assertOk();

        $this->get(route('admin.tags.edit', $tag))
            ->assertOk()
            ->assertSee('href="'.e($listUrl).'"', false);
    }

    public function test_list_urls_are_remembered_per_list(): void
    {
        $this->actingAsAdmin();
        $tag = Tag::factory()->create();

        $this->get(route('admin.tags.index', ['page' => 2]))->assertOk();
        $this->get(route('admin.articles.index', ['page' => 5]))->assertOk();

        $this->put(route('admin.tags.update', $tag), ['tag_name' => '更新後'])
            ->assertRedirect(route('admin.tags.index', ['page' => 2]));
    }

    public function test_list_with_route_parameter_is_remembered_per_parameter(): void
    {
        $this->actingAsSuperAdmin();
        $type = CustomPageType::factory()->create(['name' => 'recipe', 'base_type' => CustomPageBaseType::Article]);
        (new CustomPageSchema)->create($type);
        $listUrl = route('admin.custom-pages.entries.index', [$type, 'page' => 2]);

        $this->get($listUrl)->assertOk();

        $this->get(route('admin.custom-pages.entries.create', $type))
            ->assertOk()
            ->assertSee('href="'.e($listUrl).'"', false);
    }

    public function test_list_page_is_marked_for_scroll_restore_and_other_pages_are_not(): void
    {
        $this->actingAsAdmin();
        $tag = Tag::factory()->create();

        $this->get(route('admin.tags.index'))->assertOk()->assertSee('data-list-page', false);
        $this->get(route('admin.tags.edit', $tag))->assertOk()->assertDontSee('data-list-page', false);
    }
}
