<?php

namespace Database\Factories;

use App\Models\PageBuilderComponent;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageBuilderComponent>
 */
class PageBuilderComponentFactory extends Factory
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
            'description' => null,
            'schema_version' => SchemaMigrator::CURRENT_VERSION,
            'draft_content' => [
                'version' => SchemaMigrator::CURRENT_VERSION,
                'children' => [
                    BuilderContent::node('section', children: [BuilderContent::node('heading', ['text' => fake()->sentence()])]),
                ],
            ],
            'published_content' => null,
            'published_at' => null,
        ];
    }

    /**
     * 編集中の内容を公開済み(create() に渡した編集中の内容も公開中の内容にするため、作ったあとで公開する)。
     */
    public function published(): static
    {
        return $this->afterMaking(fn (PageBuilderComponent $component) => $component->publish());
    }
}
