<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['question_answer_id', 'question_text'])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * 質問が紐づく Q&A を取得する(最初の質問のみ。分岐先の質問は null)。
     */
    public function questionAnswer(): BelongsTo
    {
        return $this->belongsTo(QuestionAnswer::class);
    }

    /**
     * 質問の回答(選択肢)を登録順に取得する。
     */
    public function answers(): BelongsToMany
    {
        return $this->belongsToMany(Answer::class, 'branch_question_answers')
            ->using(BranchQuestionAnswer::class)
            ->withTimestamps()
            ->wherePivotNull('deleted_at')
            ->orderBy('answers.id');
    }
}
