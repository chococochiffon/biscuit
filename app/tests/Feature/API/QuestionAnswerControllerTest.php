<?php

namespace Tests\Feature\API;

use App\Models\Answer;
use App\Models\BranchQuestionAnswer;
use App\Models\Question;
use App\Models\QuestionAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionAnswerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_simple_and_branching_question_answers_in_registration_order(): void
    {
        $simple = QuestionAnswer::factory()->simple()->create([
            'short_question_text' => '送料はいくらですか?',
            'short_answer_text' => '全国一律500円です。',
        ]);
        $branching = $this->createBranchingQuestionAnswer();

        $response = $this->getJson('/api/question-answers');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0', [
            'id' => $simple->id,
            'top_view' => true,
            'short_question_text' => '送料はいくらですか?',
            'short_answer_text' => '全国一律500円です。',
            'question' => null,
        ]);
        $response->assertJsonPath('data.1.id', $branching->id);
        $response->assertJsonPath('data.1.top_view', false);
        $response->assertJsonPath('data.1.question.question_text', 'お探しの商品は?');
        $response->assertJsonPath('data.1.question.answers.0.answer_text', '本');
        $response->assertJsonPath('data.1.question.answers.0.question.question_text', 'ジャンルは?');
        $response->assertJsonPath('data.1.question.answers.0.question.answers.0.answer_text', '小説です。');
        $response->assertJsonPath('data.1.question.answers.0.question.answers.0.question', null);
        $response->assertJsonPath('data.1.question.answers.1.answer_text', '雑貨は取り扱っていません。');
        $response->assertJsonPath('data.1.question.answers.1.question', null);
    }

    public function test_index_can_filter_by_top_view(): void
    {
        $simple = QuestionAnswer::factory()->simple()->create();
        $branching = $this->createBranchingQuestionAnswer();

        $this->getJson('/api/question-answers?top_view=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $simple->id);

        $this->getJson('/api/question-answers?top_view=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $branching->id);
    }

    public function test_index_excludes_soft_deleted_question_answers(): void
    {
        QuestionAnswer::factory()->simple()->create()->delete();

        $this->getJson('/api/question-answers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_index_rejects_invalid_top_view(): void
    {
        $this->getJson('/api/question-answers?top_view=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('top_view');
    }

    /**
     * 「お探しの商品は?」→ 本(→ ジャンルは? → 小説です。)/ 雑貨は取り扱っていません。の分岐ありの Q&A を作る。
     */
    private function createBranchingQuestionAnswer(): QuestionAnswer
    {
        $questionAnswer = QuestionAnswer::factory()->create();
        $root = Question::factory()->create(['question_answer_id' => $questionAnswer->id, 'question_text' => 'お探しの商品は?']);
        $genre = Question::factory()->create(['question_text' => 'ジャンルは?']);

        $this->attachAnswer($root, Answer::factory()->create(['answer_text' => '本', 'question_id' => $genre->id]));
        $this->attachAnswer($root, Answer::factory()->create(['answer_text' => '雑貨は取り扱っていません。']));
        $this->attachAnswer($genre, Answer::factory()->create(['answer_text' => '小説です。']));

        return $questionAnswer;
    }

    private function attachAnswer(Question $question, Answer $answer): void
    {
        BranchQuestionAnswer::query()->create(['question_id' => $question->id, 'answer_id' => $answer->id]);
    }
}
