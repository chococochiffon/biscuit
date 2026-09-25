<?php

namespace Database\Factories;

use App\Models\QuestionAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionAnswer>
 */
class QuestionAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'short_question_text' => null,
            'short_answer_text' => null,
        ];
    }

    /**
     * 簡易版(トップ表示用)の Q&A にする。
     */
    public function simple(): static
    {
        return $this->state(fn () => [
            'short_question_text' => fake()->sentence().'?',
            'short_answer_text' => fake()->sentence(),
        ]);
    }
}
