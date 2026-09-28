<?php

namespace Tests\Feature;

use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_categories_in_sort_order(): void
    {
        $this->actingAsAdmin();
        $second = GalleryCategory::factory()->create(['name' => '料理', 'sort_order' => 1]);
        $first = GalleryCategory::factory()->create(['name' => '風景', 'sort_order' => 0]);

        $response = $this->getJson(route('admin.gallery-categories.index'));

        $response->assertOk();
        $this->assertSame([$first->id, $second->id], array_column($response->json(), 'id'));
        $response->assertJsonPath('0.name', '風景');
    }

    public function test_store_creates_category_at_the_end(): void
    {
        $this->actingAsAdmin();
        GalleryCategory::factory()->create(['sort_order' => 4]);

        $response = $this->postJson(route('admin.gallery-categories.store'), ['name' => '料理']);

        $response->assertCreated();
        $response->assertJsonPath('name', '料理');
        $this->assertSame(5, GalleryCategory::where('name', '料理')->firstOrFail()->sort_order);
    }

    public function test_store_rejects_duplicate_or_too_long_name(): void
    {
        $this->actingAsAdmin();
        GalleryCategory::factory()->create(['name' => '風景']);
        GalleryCategory::factory()->create(['name' => '削除済み'])->delete();

        $this->postJson(route('admin.gallery-categories.store'), ['name' => '風景'])->assertJsonValidationErrors('name');
        $this->postJson(route('admin.gallery-categories.store'), ['name' => str_repeat('あ', 129)])->assertJsonValidationErrors('name');
        // 削除済みの分類と同じ名前は登録できる
        $this->postJson(route('admin.gallery-categories.store'), ['name' => '削除済み'])->assertCreated();
    }

    public function test_update_renames_category(): void
    {
        $this->actingAsAdmin();
        $category = GalleryCategory::factory()->create(['name' => '風景']);

        $response = $this->putJson(route('admin.gallery-categories.update', $category), ['name' => '景色']);

        $response->assertOk();
        $this->assertSame('景色', $category->fresh()->name);
        // 自分自身の名前のままでも更新できる
        $this->putJson(route('admin.gallery-categories.update', $category), ['name' => '景色'])->assertOk();
    }

    public function test_destroy_soft_deletes_category_and_uncategorizes_its_images(): void
    {
        $this->actingAsAdmin();
        $category = GalleryCategory::factory()->create();
        $other = GalleryCategory::factory()->create();
        $image = GalleryImage::factory()->for($category, 'category')->create();
        $otherImage = GalleryImage::factory()->for($other, 'category')->create();

        $this->deleteJson(route('admin.gallery-categories.destroy', $category))->assertNoContent();

        $this->assertSoftDeleted($category);
        $this->assertNull($image->fresh()->gallery_category_id);
        $this->assertSame($other->id, $otherImage->fresh()->gallery_category_id);
    }

    public function test_reorder_saves_sort_order_in_submitted_order(): void
    {
        $this->actingAsAdmin();
        $first = GalleryCategory::factory()->create(['sort_order' => 0]);
        $second = GalleryCategory::factory()->create(['sort_order' => 1]);

        $this->patchJson(route('admin.gallery-categories.reorder'), ['order' => [$second->id, $first->id]])->assertNoContent();

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
    }

    public function test_reorder_rejects_deleted_category(): void
    {
        $this->actingAsAdmin();
        $category = GalleryCategory::factory()->create();
        $category->delete();

        $this->patchJson(route('admin.gallery-categories.reorder'), ['order' => [$category->id]])->assertJsonValidationErrors('order.0');
    }
}
