<?php

namespace Database\Factories;

use App\Models\ArticlePathOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticlePathOption>
 */
class ArticlePathOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->word(),
            'parent_path' => fake()->unique()->slug(2),
            'sort_order' => 0,
        ];
    }
}
