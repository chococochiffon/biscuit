<?php

namespace Database\Factories;

use App\Models\SinglePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SinglePage>
 */
class SinglePageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'short_sentences' => fake()->sentence(),
            'header_image' => null,
            'parent_path' => null,
            'slug' => fake()->unique()->slug(),
            'top_page_view' => fake()->boolean(),
            'link_list_view' => fake()->boolean(),
        ];
    }
}
