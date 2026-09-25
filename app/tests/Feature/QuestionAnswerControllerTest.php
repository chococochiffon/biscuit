<?php

namespace Tests\Feature;

use App\Models\Administrator;
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
        $actor = Administrator::factory()->create();
        QuestionAnswer::factory()->simple()->create(['short_question_text' => '送料はいくらですか?']);
        $this->storeBranch($actor, $this->branchPayload());

        $response = $this->actingAs($actor, 'admin')->get(route('admin.question-answers.index'));

        $response->assertOk();
        $response->assertSee('送料はいくらですか?');
        $response->assertSee('お探しの商品は?');
    }

    public function test_create_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.question-answers.create'));

        $response->assertOk();
    }

    public function test_store_creates_simple_question_answer_shown_on_top(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.question-answers.store'), [
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
        $actor = Administrator::factory()->create();

        $response = $this->storeBranch($actor, $this->branchPayload());

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
        $actor = Administrator::factory()->create();

        $response = $this->storeBranch($actor, [
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
        $actor = Administrator::factory()->create();

        $response = $this->storeBranch($actor, [
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

    public function test_store_simple_requires_both_short_texts(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.question-answers.store'), [
            'type' => 'simple',
            'short_question_text' => '送料はいくらですか?',
        ]);

        $response->assertSessionHasErrors('short_answer_text');
        $this->assertDatabaseCount('question_answers', 0);
    }

    public function test_show_and_edit_screens_display_question_tree(): void
    {
        $actor = Administrator::factory()->create();
        $this->storeBranch($actor, $this->branchPayload());
        $questionAnswer = QuestionAnswer::query()->sole();

        $this->actingAs($actor, 'admin')->get(route('admin.question-answers.show', $questionAnswer))
            ->assertOk()
            ->assertSee('ジャンルは?')
            ->assertSee('漫画');

        $this->actingAs($actor, 'admin')->get(route('admin.question-answers.edit', $questionAnswer))
            ->assertOk()
            ->assertSee('ジャンルは?')
            ->assertSee('漫画');
    }

    public function test_update_keeps_submitted_rows_and_soft_deletes_removed_ones(): void
    {
        $actor = Administrator::factory()->create();
        $this->storeBranch($actor, $this->branchPayload());
        $questionAnswer = QuestionAnswer::query()->sole();
        $tree = $questionAnswer->questionTree();

        $book = $tree['answers'][0];
        $goods = $tree['answers'][1];
        $novel = $book['question']['answers'][0];
        $comic = $book['question']['answers'][1];

        $response = $this->actingAs($actor, 'admin')->put(route('admin.question-answers.update', $questionAnswer), [
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
        $actor = Administrator::factory()->create();
        $this->storeBranch($actor, $this->branchPayload());
        $other = QuestionAnswer::query()->sole();
        $otherTree = $other->questionTree();

        $this->storeBranch($actor, $this->branchPayload());
        $questionAnswer = QuestionAnswer::query()->latest('id')->first();

        $this->actingAs($actor, 'admin')->put(route('admin.question-answers.update', $questionAnswer), [
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
        $actor = Administrator::factory()->create();
        $this->storeBranch($actor, $this->branchPayload());
        $questionAnswer = QuestionAnswer::query()->sole();

        $response = $this->actingAs($actor, 'admin')->put(route('admin.question-answers.update', $questionAnswer), [
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
        $actor = Administrator::factory()->create();
        $this->storeBranch($actor, $this->branchPayload());
        $questionAnswer = QuestionAnswer::query()->sole();

        $response = $this->actingAs($actor, 'admin')->delete(route('admin.question-answers.destroy', $questionAnswer));

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
    private function storeBranch(Administrator $actor, array $payload): TestResponse
    {
        return $this->actingAs($actor, 'admin')->post(route('admin.question-answers.store'), ['type' => 'branch'] + $payload);
    }
}
