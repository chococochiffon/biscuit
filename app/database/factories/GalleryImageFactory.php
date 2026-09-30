<?php

namespace Database\Factories;

use App\Enums\ArticleApprovalStatus;
use App\Models\GalleryImage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GalleryImage>
 */
class GalleryImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gallery_category_id' => null,
            'image' => GalleryImage::IMAGE_DIRECTORY.'/'.Str::random(40).'.jpg',
            'name' => fake()->words(2, true),
            'comment' => null,
            'sort_order' => 0,
            // 既定は管理者が登録した公開中の画像
            'user_id' => null,
            'approval' => ArticleApprovalStatus::Published,
        ];
    }

    /**
     * マイページからユーザーが投稿した下書きの画像にする。
     */
    public function byUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user ?? User::factory(),
            'approval' => ArticleApprovalStatus::Draft,
        ]);
    }

    /**
     * 承認待ちにする。
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval' => ArticleApprovalStatus::Pending,
        ]);
    }

    /**
     * 公開中にする。
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval' => ArticleApprovalStatus::Published,
        ]);
    }
}
