<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionAnswerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * 簡易版(top_view が true)は short_question_text・short_answer_text を持ち、question は null。
     * 分岐ありは question に最初の質問からの入れ子(質問 → answers → 分岐先の question …)を持つ。
     * 回答の question が null なら、その回答の answer_text を表示して終わる。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'top_view' => $this->top_view,
            'short_question_text' => $this->short_question_text,
            'short_answer_text' => $this->short_answer_text,
            'question' => $this->top_view ? null : $this->questionTree(),
        ];
    }
}
