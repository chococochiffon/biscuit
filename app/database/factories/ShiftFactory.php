<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_id' => Staff::query()->inRandomOrder()->first()->id,
            'work_day' => date('Y-m-d'),
            'status' => 0,
            'start_time' => date('H:i:s'),
            'end_time' => date('H:i:s'),
        ];
    }
}
