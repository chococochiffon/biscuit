<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDetail>
 */
class UserDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'family_name' => fake()->lastName(),
            'nick_name' => fake()->userName(),
            'birthday' => fake()->date(),
            'user_image' => null,
            'comment' => fake()->realText(),
            'view_flag' => true,
            'name_settings' => 1,
        ];
    }
}
