<?php

namespace App\Support\CallContent;

use App\Models\QuestionAnswer;
use Illuminate\Database\Eloquent\Collection;

/**
 * 呼び出しコンテンツ(CallContent)がQuestionAnswerを参照する場合の実データ取得を担う。
 */
class QuestionAnswerContentSource
{
    /**
     * 開閉パネル表示用に、簡易版(トップ表示用、top_view=true)の Q&A を登録順で指定件数取得する。
     *
     * @return Collection<int, QuestionAnswer>
     */
    public function getAccordion(int $count): Collection
    {
        return QuestionAnswer::query()->where('top_view', true)->oldest('id')->take($count)->get();
    }
}
