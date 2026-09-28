<?php

namespace Tests\Feature\API;

use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_images_are_returned_in_sort_order_with_category(): void
    {
        $category = GalleryCategory::factory()->create(['name' => '風景']);
        $second = GalleryImage::factory()->create(['name' => '2番目', 'sort_order' => 1]);
        $first = GalleryImage::factory()->for($category, 'category')->create(['name' => '1番目', 'comment' => 'コメント', 'sort_order' => 0]);
        GalleryImage::factory()->create()->delete();

        $response = $this->getJson(route('gallery-images.index'));

        $response->assertOk();
        $this->assertSame([$first->id, $second->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.0.name', '1番目');
        $response->assertJsonPath('data.0.comment', 'コメント');
        $response->assertJsonPath('data.0.image_url', $first->image_url);
        $response->assertJsonPath('data.0.category', ['id' => $category->id, 'name' => '風景']);
        $response->assertJsonPath('data.1.category', null);
    }

    public function test_gallery_categories_are_returned_in_sort_order(): void
    {
        $second = GalleryCategory::factory()->create(['name' => '料理', 'sort_order' => 1]);
        $first = GalleryCategory::factory()->create(['name' => '風景', 'sort_order' => 0]);

        $response = $this->getJson(route('gallery-categories.index'));

        $response->assertOk();
        $response->assertExactJson(['data' => [
            ['id' => $first->id, 'name' => '風景'],
            ['id' => $second->id, 'name' => '料理'],
        ]]);
    }
}
