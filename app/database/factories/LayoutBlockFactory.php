<?php

namespace Database\Factories;

use App\Enums\CallType;
use App\Enums\LayoutBlockType;
use App\Enums\LayoutRegion;
use App\Models\ContentModelRelation;
use App\Models\LayoutBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LayoutBlock>
 */
class LayoutBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'region' => fake()->randomElement(LayoutRegion::cases()),
            'block_type' => LayoutBlockType::Copyright,
            'title' => null,
            'subtitle' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * 呼び出しコンテンツの部品(指定がなければ記事のリンクリスト)にする。
     */
    public function callContent(CallType $callType = CallType::LinkList, ?ContentModelRelation $relation = null, int $viewCount = 5): static
    {
        return $this->state(fn () => [
            'block_type' => LayoutBlockType::CallContent,
            'call_type' => $callType,
            'content_model_relation_id' => $relation ?? ContentModelRelation::factory(),
            'view_count' => $viewCount,
        ]);
    }

    /**
     * 自由テキストの部品にする。
     */
    public function freeText(string $content = '<p>テキスト</p>'): static
    {
        return $this->state(fn () => [
            'block_type' => LayoutBlockType::FreeText,
            'content' => $content,
        ]);
    }
}
