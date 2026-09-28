<?php

namespace Tests\Feature;

use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_screen_displays_categories_in_sort_order(): void
    {
        $this->actingAsAdmin();
        GalleryCategory::factory()->create(['name' => '料理', 'sort_order' => 1]);
        GalleryCategory::factory()->create(['name' => '風景', 'sort_order' => 0]);

        $response = $this->get(route('admin.gallery-categories.edit'));

        $response->assertOk();
        $response->assertSeeInOrder(['風景', '料理']);
    }

    public function test_update_syncs_categories_creating_updating_and_deleting_rows(): void
    {
        $this->actingAsAdmin();
        $kept = GalleryCategory::factory()->create(['name' => '風景', 'sort_order' => 0]);
        $removed = GalleryCategory::factory()->create(['name' => '削除する分類', 'sort_order' => 1]);
        $imageInKept = GalleryImage::factory()->for($kept, 'category')->create();
        $imageInRemoved = GalleryImage::factory()->for($removed, 'category')->create();

        $response = $this->put(route('admin.gallery-categories.update'), [
            'categories' => [
                ['name' => '料理', 'sort_order' => 0],
                ['id' => $kept->id, 'name' => '景色', 'sort_order' => 1],
            ],
        ]);

        $response->assertRedirect(route('admin.gallery-categories.edit'));
        $this->assertSame(['料理', '景色'], GalleryCategory::query()->ordered()->pluck('name')->all());
        $this->assertSoftDeleted($removed);
        // 削除した分類の画像は未分類になり、残した分類の画像はそのまま
        $this->assertNull($imageInRemoved->fresh()->gallery_category_id);
        $this->assertSame($kept->id, $imageInKept->fresh()->gallery_category_id);
    }

    public function test_update_removes_all_categories_when_none_are_submitted(): void
    {
        $this->actingAsAdmin();
        $category = GalleryCategory::factory()->create();
        $galleryImage = GalleryImage::factory()->for($category, 'category')->create();

        $this->put(route('admin.gallery-categories.update'))->assertRedirect(route('admin.gallery-categories.edit'));

        $this->assertSoftDeleted($category);
        $this->assertNull($galleryImage->fresh()->gallery_category_id);
    }

    public function test_update_requires_category_name(): void
    {
        $this->actingAsAdmin();

        $response = $this->put(route('admin.gallery-categories.update'), [
            'categories' => [['name' => '']],
        ]);

        $response->assertSessionHasErrors('categories.0.name');
    }
}
