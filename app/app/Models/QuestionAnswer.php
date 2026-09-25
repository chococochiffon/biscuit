<?php

namespace App\Models;

use Database\Factories\QuestionAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['short_question_text', 'short_answer_text'])]
class QuestionAnswer extends Model
{
    /** @use HasFactory<QuestionAnswerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'top_view' => 'boolean',
        ];
    }

    /**
     * 簡易版(質問・回答の短文)が両方入力されていればトップ表示用にし、Q&A を削除したら配下の質問・回答も削除する。
     */
    protected static function booted(): void
    {
        static::saving(function (QuestionAnswer $questionAnswer) {
            $questionAnswer->top_view = filled($questionAnswer->short_question_text)
                && filled($questionAnswer->short_answer_text);
        });

        static::deleting(function (QuestionAnswer $questionAnswer) {
            $questionAnswer->deleteQuestionTree();
        });
    }

    /**
     * 最初の質問を取得する(分岐先の質問は回答の nextQuestion からたどる)。
     */
    public function question(): HasOne
    {
        return $this->hasOne(Question::class)->oldest('id');
    }

    /**
     * 最初の質問からたどれる質問・回答の ID をすべて取得する。
     *
     * @return array{questions: list<int>, answers: list<int>}
     */
    public function questionTreeIds(): array
    {
        $questionIds = [];
        $answerIds = [];
        $queue = $this->question ? [$this->question] : [];

        while ($question = array_shift($queue)) {
            if (in_array($question->id, $questionIds, true)) {
                continue;
            }

            $questionIds[] = $question->id;

            foreach ($question->answers as $answer) {
                $answerIds[] = $answer->id;

                if ($answer->nextQuestion) {
                    $queue[] = $answer->nextQuestion;
                }
            }
        }

        return ['questions' => $questionIds, 'answers' => $answerIds];
    }

    /**
     * 最初の質問からたどれる質問・回答を入れ子の配列で取得する(フォームの初期値・詳細画面の表示用)。
     *
     * @return array{id: int, question_text: string, answers: list<array{id: int, answer_text: ?string, question: ?array}>}|null
     */
    public function questionTree(): ?array
    {
        return $this->question ? $this->questionToArray($this->question, []) : null;
    }

    /**
     * 最初の質問からたどれる質問・回答と、その中間テーブルの行を論理削除する。
     */
    public function deleteQuestionTree(): void
    {
        ['questions' => $questionIds, 'answers' => $answerIds] = $this->questionTreeIds();

        BranchQuestionAnswer::query()->whereIn('question_id', $questionIds)->delete();
        Answer::query()->whereIn('id', $answerIds)->delete();
        Question::query()->whereIn('id', $questionIds)->delete();

        $this->unsetRelation('question');
    }

    /**
     * 質問とその回答(分岐先の質問を含む)を入れ子の配列にする。
     *
     * @param  list<int>  $visited  たどり済みの質問 ID(循環していても無限ループしないようにする)
     * @return array{id: int, question_text: string, answers: list<array{id: int, answer_text: ?string, question: ?array}>}
     */
    private function questionToArray(Question $question, array $visited): array
    {
        $visited[] = $question->id;

        return [
            'id' => $question->id,
            'question_text' => $question->question_text,
            'answers' => $question->answers->map(fn (Answer $answer) => [
                'id' => $answer->id,
                'answer_text' => $answer->answer_text,
                'question' => $answer->nextQuestion && ! in_array($answer->nextQuestion->id, $visited, true)
                    ? $this->questionToArray($answer->nextQuestion, $visited)
                    : null,
            ])->all(),
        ];
    }
}
