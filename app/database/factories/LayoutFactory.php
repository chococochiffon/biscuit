<?php

namespace Database\Factories;

use App\Enums\LayoutPageType;
use App\Enums\SidebarPosition;
use App\Models\Layout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Layout>
 */
class LayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_type' => fake()->randomElement(LayoutPageType::cases()),
            'sidebar_position' => SidebarPosition::None,
        ];
    }
}
