<?php

namespace Database\Factories;

use App\Enums\CallContentType;
use App\Models\ContentModelRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentModelRelation>
 */
class ContentModelRelationFactory extends Factory
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
            'table_name' => fake()->word(),
        ];
    }
}
