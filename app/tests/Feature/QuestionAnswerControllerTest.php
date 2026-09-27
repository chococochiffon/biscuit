<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\BranchQuestionAnswer;
use App\Models\Question;
use App\Models\QuestionAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class QuestionAnswerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_question_answer_pages(): void
    {
        $response = $this->get(route('admin.question-answers.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_index_displays_question_answers(): void
    {
        $this->actingAsAdmin();
        QuestionAnswer::factory()->simple()->create(['short_question_text' => '送料はいくらですか?']);
        $this->storeBranch($this->branchPayload());

        $response = $this->get(route('admin.question-answers.index'));

        $response->assertOk();
        $response->assertSee('送料はいくらですか?');
        $response->assertSee('お探しの商品は?');
    }

    public function test_create_screen_can_be_rendered(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.question-answers.create'));

        $response->assertOk();
    }

    public function test_store_creates_simple_question_answer_shown_on_top(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.question-answers.store'), [
            'type' => 'simple',
            'short_question_text' => '送料はいくらですか?',
            'short_answer_text' => '全国一律500円です。',
            'question' => $this->branchPayload()['question'],
        ]);

        $response->assertRedirect(route('admin.question-answers.index'));
        $this->assertDatabaseHas('question_answers', [
            'short_question_text' => '送料はいくらですか?',
            'short_answer_text' => '全国一律500円です。',
            'top_view' => true,
        ]);
        $this->assertDatabaseCount('questions', 0);
        $this->assertDatabaseCount('answers', 0);
    }

    public function test_store_creates_branching_questions_and_answers(): void
    {
        $this->actingAsAdmin();

        $response = $this->storeBranch($this->branchPayload());

        $response->assertRedirect(route('admin.question-answers.index'));

        $questionAnswer = QuestionAnswer::query()->sole();
        $this->assertFalse($questionAnswer->top_view);
        $this->assertNull($questionAnswer->short_question_text);

        $root = Question::query()->where('question_text', 'お探しの商品は?')->sole();
        $this->assertSame($questionAnswer->id, $root->question_answer_id);
        $this->assertSame(['本', '雑貨'], $root->answers->pluck('answer_text')->all());

        $branchAnswer = $root->answers->firstWhere('answer_text', '本');
        $this->assertSame('ジャンルは?', $branchAnswer->nextQuestion->question_text);
        $this->assertNull($branchAnswer->nextQuestion->question_answer_id);
        $this->assertSame(['小説', '漫画'], $branchAnswer->nextQuestion->answers->pluck('answer_text')->all());

        $this->assertNull($root->answers->firstWhere('answer_text', '雑貨')->question_id);
        $this->assertDatabaseCount('branch_question_answers', 4);
    }

    public function test_store_requires_answer_text_when_answer_has_no_branch(): void
    {
        $this->actingAsAdmin();

        $response = $this->storeBranch([
            'question' => [
                'question_text' => 'お探しの商品は?',
                'answers' => [
                    ['answer_text' => null],
                    ['answer_text' => null, 'question' => [
                        'question_text' => 'ジャンルは?',
                        'answers' => [['answer_text' => '小説']],
                    ]],
                ],
            ],
        ]);

        $response->assertSessionHasErrors('question.answers.0.answer_text');
        $response->assertSessionDoesntHaveErrors('question.answers.1.answer_text');
        $this->assertDatabaseCount('question_answers', 0);
    }

    public function test_store_requires_question_text_and_answers_of_branch_question(): void
    {
        $this->actingAsAdmin();

        $response = $this->storeBranch([
            'question' => [
                'question_text' => 'お探しの商品は?',
                'answers' => [
                    ['answer_text' => '本', 'question' => ['question_text' => null]],
                ],
            ],
        ]);

        $response->assertSessionHasErrors([
            'question.answers.0.question.question_text',
            'question.answers.0.question.answers',
        ]);
    }

    public function test_store_rejects_answers_over_the_limit_at_any_depth(): void
    {
        config(['limits.question_answers' => 2]);
        $this->actingAsAdmin();

        $response = $this->storeBranch([
            'question' => [
                'question_text' => 'お探しの商品は?',
                'answers' => [
                    ['answer_text' => '本'],
                    ['answer_text' => null, 'question' => [
                        'question_text' => 'ジャンルは?',
                        'answers' => [['answer_text' => '小説'], ['answer_text' => '漫画'], ['answer_text' => '雑誌']],
                    ]],
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['question.answers.1.question.answers' => '1つの質問に登録できる回答は2件までです。']);
        $response->assertSessionDoesntHaveErrors('question.answers');
        $this->assertDatabaseCount('question_answers', 0);
    }

    public function test_create_screen_passes_answer_limit_to_the_form(): void
    {
        config(['limits.question_answers' => 3]);
        $this->actingAsAdmin();

        $response = $this->get(route('admin.question-answers.create'));

        $response->assertSee('data-max-answers="3"', false);
        $response->assertSee('1つの質問に登録できる回答は3件までです。');
    }

    public function test_store_simple_requires_both_short_texts(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.question-answers.store'), [
            'type' => 'simple',
            'short_question_text' => '送料はいくらですか?',
        ]);

        $response->assertSessionHasErrors('short_answer_text');
        $this->assertDatabaseCount('question_answers', 0);
    }

    public function test_show_and_edit_screens_display_question_tree(): void
    {
        $this->actingAsAdmin();
        $this->storeBranch($this->branchPayload());
        $questionAnswer = QuestionAnswer::query()->sole();

        $this->get(route('admin.question-answers.show', $questionAnswer))
            ->assertOk()
            ->assertSee('ジャンルは?')
            ->assertSee('漫画');

        $this->get(route('admin.question-answers.edit', $questionAnswer))
            ->assertOk()
            ->assertSee('ジャンルは?')
            ->assertSee('漫画');
    }

    public function test_update_keeps_submitted_rows_and_soft_deletes_removed_ones(): void
    {
        $this->actingAsAdmin();
        $this->storeBranch($this->branchPayload());
        $questionAnswer = QuestionAnswer::query()->sole();
        $tree = $questionAnswer->questionTree();

        $book = $tree['answers'][0];
        $goods = $tree['answers'][1];
        $novel = $book['question']['answers'][0];
        $comic = $book['question']['answers'][1];

        $response = $this->put(route('admin.question-answers.update', $questionAnswer), [
            'type' => 'branch',
            'question' => [
                'id' => $tree['id'],
                'question_text' => 'お探しのものは?',
                'answers' => [
                    ['id' => $book['id'], 'answer_text' => '本', 'question' => [
                        'id' => $book['question']['id'],
                        'question_text' => 'ジャンルは?',
                        'answers' => [
                            ['id' => $novel['id'], 'answer_text' => '小説です'],
                        ],
                    ]],
                    ['id' => $goods['id'], 'answer_text' => '雑貨', 'question' => [
                        'question_text' => '用途は?',
                        'answers' => [['answer_text' => 'ギフト']],
                    ]],
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.question-answers.index'));

        $this->assertDatabaseHas('questions', ['id' => $tree['id'], 'question_text' => 'お探しのものは?']);
        $this->assertDatabaseHas('answers', ['id' => $novel['id'], 'answer_text' => '小説です']);
        $this->assertSoftDeleted('answers', ['id' => $comic['id']]);
        $this->assertSoftDeleted('branch_question_answers', ['answer_id' => $comic['id']]);

        $updatedTree = $questionAnswer->fresh()->questionTree();
        $this->assertSame(['小説です'], array_column($updatedTree['answers'][0]['question']['answers'], 'answer_text'));
        $this->assertSame($goods['id'], $updatedTree['answers'][1]['id']);
        $this->assertSame('用途は?', $updatedTree['answers'][1]['question']['question_text']);
    }

    public function test_update_creates_new_rows_for_ids_outside_the_question_answer(): void
    {
        $this->actingAsAdmin();
        $this->storeBranch($this->branchPayload());
        $other = QuestionAnswer::query()->sole();
        $otherTree = $other->questionTree();

        $this->storeBranch($this->branchPayload());
        $questionAnswer = QuestionAnswer::query()->latest('id')->first();

        $this->put(route('admin.question-answers.update', $questionAnswer), [
            'type' => 'branch',
            'question' => [
                'id' => $otherTree['id'],
                'question_text' => '乗っ取り',
                'answers' => [['id' => $otherTree['answers'][0]['id'], 'answer_text' => '乗っ取り']],
            ],
        ]);

        $this->assertSame($otherTree, $other->fresh()->questionTree());
        $this->assertSame('乗っ取り', $questionAnswer->fresh()->questionTree()['question_text']);
    }

    public function test_update_to_simple_soft_deletes_question_tree(): void
    {
        $this->actingAsAdmin();
        $this->storeBranch($this->branchPayload());
        $questionAnswer = QuestionAnswer::query()->sole();

        $response = $this->put(route('admin.question-answers.update', $questionAnswer), [
            'type' => 'simple',
            'short_question_text' => '送料はいくらですか?',
            'short_answer_text' => '全国一律500円です。',
        ]);

        $response->assertRedirect(route('admin.question-answers.index'));
        $this->assertTrue($questionAnswer->fresh()->top_view);
        $this->assertSame(0, Question::query()->count());
        $this->assertSame(0, Answer::query()->count());
        $this->assertSame(0, BranchQuestionAnswer::query()->count());
        $this->assertSame(2, Question::onlyTrashed()->count());
    }

    public function test_destroy_soft_deletes_question_answer_and_its_tree(): void
    {
        $this->actingAsAdmin();
        $this->storeBranch($this->branchPayload());
        $questionAnswer = QuestionAnswer::query()->sole();

        $response = $this->delete(route('admin.question-answers.destroy', $questionAnswer));

        $response->assertRedirect(route('admin.question-answers.index'));
        $this->assertSoftDeleted($questionAnswer);
        $this->assertSame(0, Question::query()->count());
        $this->assertSame(0, Answer::query()->count());
        $this->assertSame(0, BranchQuestionAnswer::query()->count());
    }

    /**
     * 「お探しの商品は?」→ 本(→ ジャンルは? → 小説/漫画)/ 雑貨 の分岐ありの Q&A の入力。
     *
     * @return array<string, mixed>
     */
    private function branchPayload(): array
    {
        return [
            'question' => [
                'question_text' => 'お探しの商品は?',
                'answers' => [
                    ['answer_text' => '本', 'question' => [
                        'question_text' => 'ジャンルは?',
                        'answers' => [
                            ['answer_text' => '小説'],
                            ['answer_text' => '漫画'],
                        ],
                    ]],
                    ['answer_text' => '雑貨'],
                ],
            ],
        ];
    }

    /**
     * 分岐ありの Q&A を登録する。
     *
     * @param  array<string, mixed>  $payload
     */
    private function storeBranch(array $payload): TestResponse
    {
        return $this->post(route('admin.question-answers.store'), ['type' => 'branch'] + $payload);
    }
}
