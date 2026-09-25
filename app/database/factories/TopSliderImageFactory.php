<?php

namespace Database\Factories;

use App\Models\TopSliderImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TopSliderImage>
 */
class TopSliderImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'top_image' => TopSliderImage::IMAGE_DIRECTORY.'/'.Str::random(40).'.jpg',
            'url' => null,
            'sort_order' => 0,
        ];
    }
}
