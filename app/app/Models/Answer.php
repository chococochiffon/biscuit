<?php

namespace App\Models;

use Database\Factories\AnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['question_id', 'answer_text'])]
class Answer extends Model
{
    /** @use HasFactory<AnswerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * 回答後に分岐する次の質問を取得する(null なら answer_text を表示して終わる)。
     */
    public function nextQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
