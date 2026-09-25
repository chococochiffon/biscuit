<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionAnswerResource;
use App\Models\QuestionAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class QuestionAnswerController extends Controller
{
    /**
     * Q&A 一覧を登録順で取得する。top_view で簡易版(トップ表示用)だけ・分岐ありだけに絞り込める。
     * 分岐ありの Q&A は、最初の質問からの質問・回答を入れ子で含む。
     */
    #[OA\Get(
        path: '/question-answers',
        summary: 'Q&A 一覧を取得する',
        tags: ['QuestionAnswers'],
        parameters: [
            new OA\Parameter(name: 'top_view', in: 'query', required: false, description: '1:簡易版(トップ表示用)だけ, 0:分岐ありだけ。未指定時はすべて', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Q&A 一覧(登録順)。各要素は id・top_view・short_question_text・short_answer_text(簡易版のみ)と、question(分岐ありのみ。{id, question_text, answers: [{id, answer_text, question}]} の入れ子で、回答の question が null ならその answer_text を表示して終わる)'),
            new OA\Response(response: 422, description: 'top_viewの値が不正'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'top_view' => ['sometimes', 'boolean'],
        ]);

        $questionAnswers = QuestionAnswer::query()
            ->when(isset($validated['top_view']), fn ($query) => $query->where('top_view', (bool) $validated['top_view']))
            ->oldest('id')
            ->get();

        return QuestionAnswerResource::collection($questionAnswers);
    }
}
