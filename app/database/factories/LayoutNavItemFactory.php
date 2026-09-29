<?php

namespace Database\Factories;

use App\Enums\LayoutBlockType;
use App\Enums\NavItemLinkType;
use App\Models\LayoutBlock;
use App\Models\LayoutNavItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LayoutNavItem>
 */
class LayoutNavItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'layout_block_id' => LayoutBlock::factory()->state(['block_type' => LayoutBlockType::NavMenu]),
            'link_type' => NavItemLinkType::Url,
            'label' => fake()->word(),
            'url' => '/'.fake()->slug(),
            'sort_order' => 0,
        ];
    }
}
