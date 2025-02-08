<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StaffRole>
 */
class StaffRoleFactory extends Factory
{
    protected $model = StaffRole::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_id' => Staff::query()->inRandomOrder()->first()->id,
            'role_id' => Role::query()->inRandomOrder()->first()->id,
        ];
    }
}
