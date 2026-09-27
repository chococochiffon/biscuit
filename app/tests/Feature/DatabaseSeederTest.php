<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\SinglePage;
use App\Models\SiteSetting;
use App\Models\TopSliderImage;
use Database\Seeders\TopSliderImageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_all_initial_data_including_images(): void
    {
        Storage::fake('public');

        // DatabaseSeeder は WithoutModelEvents で各シーダーを実行するため、イベント頼みの処理が漏れていないかを通しで確認する
        $this->seed();

        Storage::disk('public')->assertExists([
            Article::DEFAULT_THUMBNAIL_PATH,
            SiteSetting::DEFAULT_SITE_ICON_PATH,
            SiteSetting::DEFAULT_SITE_IMAGE_PATH,
        ]);

        $sliderImages = TopSliderImage::query()->ordered()->get();
        $this->assertCount(3, $sliderImages);

        foreach ($sliderImages as $sliderImage) {
            $this->assertSame([1920, 1080], array_slice(getimagesizefromstring(Storage::disk('public')->get($sliderImage->top_image)), 0, 2));
        }

        $this->assertSame(['/information/about'], SinglePage::query()->pluck('path')->all());
        $this->assertSame(3, Article::query()->whereNotNull('path')->count());

        $this->getJson(route('api.resolve', ['path' => '/news/biscuit-v1-0-release']))
            ->assertOk()
            ->assertJsonPath('type', 'article');
    }

    public function test_top_slider_images_are_not_duplicated_when_seeding_again(): void
    {
        Storage::fake('public');

        $this->seed(TopSliderImageSeeder::class);
        $this->seed(TopSliderImageSeeder::class);

        $this->assertSame(3, TopSliderImage::count());
    }
}
