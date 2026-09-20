<?php

namespace Database\Factories;

use App\Enums\CallContentType;
use App\Models\CallContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallContent>
 */
class CallContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_type' => fake()->randomElement(CallContentType::cases()),
            'model_name' => fake()->word(),
            'view_count' => 1,
            'place' => fake()->word(),
        ];
    }
}
