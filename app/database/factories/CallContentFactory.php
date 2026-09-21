<?php

namespace Database\Factories;

use App\Enums\CallContentPlace;
use App\Enums\CallType;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
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
            'call_type' => fake()->randomElement(CallType::cases()),
            'call_name' => fake()->word(),
            'content_model_relation_id' => ContentModelRelation::factory(),
            'view_count' => 1,
            'place' => fake()->randomElement(CallContentPlace::cases()),
        ];
    }
}
