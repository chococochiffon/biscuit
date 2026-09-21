<?php

namespace Database\Factories;

use App\Models\SinglePage;
use App\Models\SinglePageDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SinglePageDetail>
 */
class SinglePageDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'single_page_id' => SinglePage::factory(),
            'sub_title' => fake()->sentence(),
            'contents' => fake()->paragraphs(3, true),
        ];
    }
}
