<?php

namespace Database\Factories;

use App\Enums\SocialService;
use App\Models\SocialLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialLink>
 */
class SocialLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service' => fake()->randomElement(SocialService::cases()),
            'name' => fake()->word(),
            'url' => fake()->url(),
            'sort_order' => 0,
        ];
    }
}
