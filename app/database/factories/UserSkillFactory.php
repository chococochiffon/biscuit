<?php

namespace Database\Factories;

use App\Models\UserDetail;
use App\Models\UserSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSkill>
 */
class UserSkillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_detail_id' => UserDetail::factory(),
            'name' => fake()->word(),
            'level' => fake()->numberBetween(0, UserSkill::MAX_LEVEL),
            'sort_order' => 0,
        ];
    }
}
