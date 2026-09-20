<?php

namespace Database\Factories;

use App\Enums\ArticleApprovalStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'content' => fake()->paragraphs(3, true),
            'thumbnail' => null,
            'user_id' => User::factory(),
            'approval' => ArticleApprovalStatus::Draft,
        ];
    }

    /**
     * Indicate that the article is pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval' => ArticleApprovalStatus::Pending,
        ]);
    }

    /**
     * Indicate that the article is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval' => ArticleApprovalStatus::Published,
        ]);
    }

    /**
     * Indicate that the article was registered by an administrator (no user_id).
     */
    public function byAdministrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
