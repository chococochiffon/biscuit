<?php

namespace Database\Factories;

use App\Models\GalleryImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GalleryImage>
 */
class GalleryImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gallery_category_id' => null,
            'image' => GalleryImage::IMAGE_DIRECTORY.'/'.Str::random(40).'.jpg',
            'name' => fake()->words(2, true),
            'comment' => null,
            'sort_order' => 0,
        ];
    }
}
