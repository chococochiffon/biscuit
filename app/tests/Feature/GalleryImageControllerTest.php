<?php

namespace Tests\Feature;

use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryImageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_gallery_pages(): void
    {
        $this->get(route('admin.gallery-images.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.gallery-categories.edit'))->assertRedirect(route('admin.login'));
    }

    public function test_index_displays_gallery_images_in_sort_order(): void
    {
        $this->actingAsAdmin();
        $category = GalleryCategory::factory()->create(['name' => '風景']);
        GalleryImage::factory()->create(['name' => '2番目', 'sort_order' => 1]);
        GalleryImage::factory()->for($category, 'category')->create(['name' => '1番目', 'sort_order' => 0]);

        $response = $this->get(route('admin.gallery-images.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['1番目', '風景', '2番目', '未分類']);
    }

    public function test_create_screen_lists_categories_in_sort_order(): void
    {
        $this->actingAsAdmin();
        GalleryCategory::factory()->create(['name' => '料理', 'sort_order' => 1]);
        GalleryCategory::factory()->create(['name' => '風景', 'sort_order' => 0]);

        $response = $this->get(route('admin.gallery-images.create'));

        $response->assertOk();
        $response->assertSeeInOrder(['未分類', '風景', '料理']);
    }

    public function test_store_saves_image_scaled_down_within_max_size_at_the_end(): void
    {
        $this->actingAsAdmin();
        Storage::fake('public');
        $category = GalleryCategory::factory()->create();
        GalleryImage::factory()->create(['sort_order' => 3]);

        $response = $this->post(route('admin.gallery-images.store'), [
            'image' => UploadedFile::fake()->image('wide.jpg', 2400, 1200),
            'name' => '海辺',
            'comment' => '夏の海です。',
            'gallery_category_id' => $category->id,
        ]);

        $response->assertRedirect(route('admin.gallery-images.index'));
        $galleryImage = GalleryImage::where('name', '海辺')->firstOrFail();
        $this->assertSame($category->id, $galleryImage->gallery_category_id);
        $this->assertSame('夏の海です。', $galleryImage->comment);
        $this->assertSame(4, $galleryImage->sort_order);
        $this->assertStringStartsWith(GalleryImage::IMAGE_DIRECTORY.'/', $galleryImage->image);
        $this->assertSame([1200, 600], array_slice(getimagesizefromstring(Storage::disk('public')->get($galleryImage->image)), 0, 2));
    }

    public function test_store_does_not_enlarge_small_images(): void
    {
        $this->actingAsAdmin();
        Storage::fake('public');

        $this->post(route('admin.gallery-images.store'), [
            'image' => UploadedFile::fake()->image('small.png', 300, 200),
            'name' => '小さい画像',
        ])->assertSessionHasNoErrors();

        $galleryImage = GalleryImage::where('name', '小さい画像')->firstOrFail();
        $this->assertNull($galleryImage->gallery_category_id);
        $this->assertSame([300, 200], array_slice(getimagesizefromstring(Storage::disk('public')->get($galleryImage->image)), 0, 2));
    }

    public function test_store_requires_image_and_name(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.gallery-images.store'), [
            'name' => str_repeat('あ', 129),
        ]);

        $response->assertSessionHasErrors(['image', 'name']);
        $this->assertDatabaseCount('gallery_images', 0);
    }

    public function test_store_rejects_deleted_category(): void
    {
        $this->actingAsAdmin();
        $category = GalleryCategory::factory()->create();
        $category->delete();

        $response = $this->post(route('admin.gallery-images.store'), [
            'image' => UploadedFile::fake()->image('photo.jpg'),
            'name' => '写真',
            'gallery_category_id' => $category->id,
        ]);

        $response->assertSessionHasErrors('gallery_category_id');
    }

    public function test_update_keeps_image_when_no_file_is_submitted(): void
    {
        $this->actingAsAdmin();
        $galleryImage = GalleryImage::factory()->for(GalleryCategory::factory(), 'category')->create(['image' => 'image/gallery/keep.jpg']);

        $response = $this->put(route('admin.gallery-images.update', $galleryImage), [
            'name' => '新しい名前',
            'comment' => '',
            'gallery_category_id' => '',
        ]);

        $response->assertRedirect(route('admin.gallery-images.index'));
        $galleryImage->refresh();
        $this->assertSame('新しい名前', $galleryImage->name);
        $this->assertNull($galleryImage->comment);
        $this->assertNull($galleryImage->gallery_category_id);
        $this->assertSame('image/gallery/keep.jpg', $galleryImage->image);
    }

    public function test_update_replaces_image_when_file_is_submitted(): void
    {
        $this->actingAsAdmin();
        Storage::fake('public');
        $galleryImage = GalleryImage::factory()->create(['image' => 'image/gallery/old.jpg']);

        $this->put(route('admin.gallery-images.update', $galleryImage), [
            'image' => UploadedFile::fake()->image('new.jpg', 800, 800),
            'name' => $galleryImage->name,
        ])->assertSessionHasNoErrors();

        $this->assertNotSame('image/gallery/old.jpg', $galleryImage->fresh()->image);
        Storage::disk('public')->assertExists($galleryImage->fresh()->image);
    }

    public function test_destroy_soft_deletes_gallery_image(): void
    {
        $this->actingAsAdmin();
        $galleryImage = GalleryImage::factory()->create();

        $this->delete(route('admin.gallery-images.destroy', $galleryImage))
            ->assertRedirect(route('admin.gallery-images.index'));

        $this->assertSoftDeleted($galleryImage);
    }

    public function test_reorder_saves_sort_order_from_the_page_offset(): void
    {
        $this->actingAsAdmin();
        $first = GalleryImage::factory()->create(['sort_order' => 20]);
        $second = GalleryImage::factory()->create(['sort_order' => 21]);

        $response = $this->patch(route('admin.gallery-images.reorder'), [
            'order' => [$second->id, $first->id],
            'offset' => 20,
            'page' => 2,
        ]);

        $response->assertRedirect(route('admin.gallery-images.index', ['page' => 2]));
        $this->assertSame(20, $second->fresh()->sort_order);
        $this->assertSame(21, $first->fresh()->sort_order);
    }
}
