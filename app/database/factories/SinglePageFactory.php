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
            'content' => fake()->paragraphs(5, true),
            'short_sentences' => fake()->sentence(),
            'header_image' => null,
            'taxonomy' => fake()->word(),
            'url' => fake()->slug(),
        ];
    }
}
