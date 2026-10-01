<?php

namespace Database\Factories;

use App\Models\PageBuilderTemplate;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageBuilderTemplate>
 */
class PageBuilderTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'schema_version' => SchemaMigrator::CURRENT_VERSION,
            'content' => [
                'version' => SchemaMigrator::CURRENT_VERSION,
                'children' => [
                    BuilderContent::node('section', children: [BuilderContent::node('heading', ['text' => fake()->sentence()])]),
                ],
            ],
        ];
    }
}
