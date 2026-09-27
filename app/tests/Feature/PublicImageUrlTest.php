<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\SinglePage;
use App\Models\SiteSetting;
use App\Models\TopSliderImage;
use App\Models\UserDetail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicImageUrlTest extends TestCase
{
    public function test_image_url_accessors_return_public_urls_of_stored_images(): void
    {
        $url = fn (string $path) => Storage::disk('public')->url($path);

        $this->assertSame($url('image/thumbnail/a.png'), (new Article(['thumbnail' => 'image/thumbnail/a.png']))->thumbnail_url);
        $this->assertSame($url('image/header_image/a.png'), (new SinglePage(['header_image' => 'image/header_image/a.png']))->header_image_url);
        $this->assertSame($url('image/site_icon/a.png'), (new SiteSetting(['site_icon' => 'image/site_icon/a.png']))->site_icon_url);
        $this->assertSame($url('image/site_image/a.png'), (new SiteSetting(['site_image' => 'image/site_image/a.png']))->site_image_url);
        $this->assertSame($url('image/user/a.png'), (new UserDetail(['user_image' => 'image/user/a.png']))->user_image_url);
        $this->assertSame($url('image/top_image/a.png'), (new TopSliderImage(['top_image' => 'image/top_image/a.png']))->top_image_url);
    }

    public function test_unset_images_fall_back_to_default_images_or_null(): void
    {
        $url = fn (string $path) => Storage::disk('public')->url($path);

        // デフォルト画像があるものはデフォルト画像、ないものは null
        $this->assertSame($url(Article::DEFAULT_THUMBNAIL_PATH), (new Article)->thumbnail_url);
        $this->assertSame($url(SiteSetting::DEFAULT_SITE_ICON_PATH), (new SiteSetting)->site_icon_url);
        $this->assertSame($url(SiteSetting::DEFAULT_SITE_IMAGE_PATH), (new SiteSetting)->site_image_url);
        $this->assertNull((new SinglePage)->header_image_url);
        $this->assertNull((new UserDetail)->user_image_url);
    }
}
