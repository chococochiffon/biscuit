<?php

namespace Database\Factories;

use App\Enums\BuilderComponentKind;
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
            'kind' => BuilderComponentKind::Global,
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
     * 独自コンポーネント(画像・見出し・本文のカードで、どれも差し替えられる項目にしたもの)。
     */
    public function custom(): static
    {
        return $this->state(function () {
            $image = BuilderContent::node('image', ['alt' => 'カードの画像']);
            $image['exposed'] = ['src' => '画像'];
            $heading = BuilderContent::node('heading', ['text' => 'カードの見出し', 'level' => 3]);
            $heading['exposed'] = ['text' => '見出し'];
            $text = BuilderContent::node('text', ['html' => '<p>カードの本文</p>']);
            $text['exposed'] = ['html' => '本文'];

            return [
                'kind' => BuilderComponentKind::Custom,
                'draft_content' => ['version' => SchemaMigrator::CURRENT_VERSION, 'children' => [$image, $heading, $text]],
            ];
        });
    }

    /**
     * 編集中の内容を公開済み(create() に渡した編集中の内容も公開中の内容にするため、作ったあとで公開する)。
     */
    public function published(): static
    {
        return $this->afterMaking(fn (PageBuilderComponent $component) => $component->publish());
    }
}
