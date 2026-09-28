<?php

namespace Database\Factories;

use App\Enums\CustomPageBaseType;
use App\Models\CustomPageType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomPageType>
 */
class CustomPageTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     * テーブルは作らないため、テーブルが必要なテストでは CustomPageSchema::create() を呼ぶ。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lexify('type_????'),
            'label' => fake()->word(),
            'base_type' => CustomPageBaseType::Article,
            'sort_order' => 0,
        ];
    }

    /**
     * 固定ページ型にする。
     */
    public function singlePage(): static
    {
        return $this->state(fn (array $attributes) => [
            'base_type' => CustomPageBaseType::SinglePage,
        ]);
    }
}
