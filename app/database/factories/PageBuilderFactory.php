<?php

namespace Database\Factories;

use App\Enums\BuilderPageType;
use App\Models\PageBuilder;
use App\Models\SinglePage;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageBuilder>
 */
class PageBuilderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_type' => BuilderPageType::SinglePage,
            'single_page_id' => SinglePage::factory(),
            'schema_version' => SchemaMigrator::CURRENT_VERSION,
            'draft_content' => [
                'version' => SchemaMigrator::CURRENT_VERSION,
                'children' => [
                    BuilderContent::node('section', children: [
                        BuilderContent::node('heading', ['text' => fake()->sentence()]),
                    ]),
                ],
            ],
            'published_content' => null,
            'published_at' => null,
        ];
    }

    /**
     * トップのビルダー。
     */
    public function top(): static
    {
        return $this->state(fn () => [
            'page_type' => BuilderPageType::Top,
            'single_page_id' => null,
        ]);
    }

    /**
     * 編集中の内容を公開済み。
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_content' => $attributes['draft_content'],
            'published_at' => now(),
        ]);
    }
}
