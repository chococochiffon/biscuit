<?php

namespace Tests\Feature;

use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use App\Models\PageBuilder;
use App\Models\SinglePage;
use App\Support\Builder\BlockDataResolver;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryBlockTest extends TestCase
{
    use RefreshDatabase;

    private GalleryCategory $illustration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->illustration = GalleryCategory::factory()->create(['name' => 'イラスト']);
        GalleryImage::factory()->create(['name' => '写真', 'sort_order' => 0]);
        GalleryImage::factory()->create(['name' => '絵 2', 'gallery_category_id' => $this->illustration->id, 'sort_order' => 2]);
        GalleryImage::factory()->create(['name' => '絵 1', 'gallery_category_id' => $this->illustration->id, 'sort_order' => 1]);
        GalleryImage::factory()->byUser()->create(['name' => '下書き', 'gallery_category_id' => $this->illustration->id]);
    }

    public function test_images_follow_the_conditions_in_display_order(): void
    {
        $names = fn (array $props) => BlockDataResolver::galleryImages($props)->pluck('name')->all();

        $this->assertSame(['写真', '絵 1', '絵 2'], $names([]));
        $this->assertSame(['絵 1', '絵 2'], $names(['category' => $this->illustration->id]));
        $this->assertSame(['写真', '絵 1'], $names(['limit' => 2]));
        $this->assertSame([], $names(['category' => 9999]));
    }

    public function test_resolve_returns_the_images_of_the_block_without_saving_them(): void
    {
        $singlePage = SinglePage::factory()->create(['slug' => 'gallery-page', 'use_builder' => true]);
        $builder = PageBuilder::factory()->published()->create([
            'single_page_id' => $singlePage->id,
            'draft_content' => BuilderContent::withDefaultLayout([
                'version' => SchemaMigrator::CURRENT_VERSION,
                'children' => [BuilderContent::node('section', children: [BuilderContent::node('gallery', ['category' => $this->illustration->id, 'columns' => 3])])],
            ]),
        ]);

        $this->getJson(route('api.resolve', ['path' => '/gallery-page']))
            ->assertOk()
            ->assertJsonPath('data.builder.children.0.children.0.type', 'gallery')
            ->assertJsonPath('data.builder.children.0.children.0.props.columns', 3)
            ->assertJsonCount(2, 'data.builder.children.0.children.0.data.images')
            ->assertJsonPath('data.builder.children.0.children.0.data.images.0.name', '絵 1')
            ->assertJsonPath('data.builder.children.0.children.0.data.images.0.category.name', 'イラスト');

        $this->assertArrayNotHasKey('data', $builder->fresh()->published_content['children'][0]['children'][0]);
    }

    public function test_editor_receives_the_categories_and_the_preview_images(): void
    {
        $this->getJson(route('admin.json.builder.gallery'))->assertUnauthorized();

        $this->actingAsAdmin();

        $this->getJson(route('admin.json.builder.top.show'))
            ->assertOk()
            ->assertJsonPath('gallery_categories', [['id' => $this->illustration->id, 'name' => 'イラスト']]);

        $this->getJson(route('admin.json.builder.gallery', ['category' => $this->illustration->id, 'limit' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'images')
            ->assertJsonPath('images.0.name', '絵 1');
    }

    public function test_category_accepts_an_id_or_null(): void
    {
        $this->actingAsAdmin();
        $content = fn (mixed $category) => BuilderContent::withDefaultLayout([
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [BuilderContent::node('section', children: [BuilderContent::node('gallery', ['category' => $category])])],
        ]);

        $this->putJson(route('admin.json.builder.top.update'), ['content' => $content(null), 'updated_at' => null])->assertOk();
        $updatedAt = PageBuilder::top()->updated_at->toIso8601String();
        $this->putJson(route('admin.json.builder.top.update'), ['content' => $content($this->illustration->id), 'updated_at' => $updatedAt])->assertOk();
        $this->putJson(route('admin.json.builder.top.update'), ['content' => $content('イラスト'), 'updated_at' => PageBuilder::top()->updated_at->toIso8601String()])->assertUnprocessable();
    }
}
