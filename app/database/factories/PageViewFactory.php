<?php

namespace Database\Factories;

use App\Models\PageView;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PageView>
 */
class PageViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_type' => 'top',
            'content_id' => null,
            'path' => '/',
            'visitor_id' => (string) Str::uuid(),
            'viewed_at' => now(),
        ];
    }

    /**
     * 記事・固定ページなどのコンテンツの PV にする。
     */
    public function forContent(string $contentType, int $contentId, string $path = '/page'): static
    {
        return $this->state(fn () => [
            'content_type' => $contentType,
            'content_id' => $contentId,
            'path' => $path,
        ]);
    }
}
