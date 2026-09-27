<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\SiteSetting;
use Database\Seeders\DefaultImageSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DefaultImageSeederTest extends TestCase
{
    public function test_places_default_images_at_the_paths_used_by_models(): void
    {
        Storage::fake('public');

        $this->seed(DefaultImageSeeder::class);

        foreach ([Article::DEFAULT_THUMBNAIL_PATH, SiteSetting::DEFAULT_SITE_ICON_PATH, SiteSetting::DEFAULT_SITE_IMAGE_PATH] as $path) {
            $this->assertSame(
                File::get(DefaultImageSeeder::sourcePath(basename($path))),
                Storage::disk('public')->get($path)
            );
        }
    }

    public function test_does_not_overwrite_existing_default_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(Article::DEFAULT_THUMBNAIL_PATH, 'replaced');

        $this->seed(DefaultImageSeeder::class);

        $this->assertSame('replaced', Storage::disk('public')->get(Article::DEFAULT_THUMBNAIL_PATH));
    }
}
