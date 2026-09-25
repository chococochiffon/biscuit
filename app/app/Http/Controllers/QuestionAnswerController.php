<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionAnswerRequest;
use App\Http\Requests\UpdateQuestionAnswerRequest;
use App\Models\Answer;
use App\Models\BranchQuestionAnswer;
use App\Models\Question;
use App\Models\QuestionAnswer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuestionAnswerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $questionAnswers = QuestionAnswer::query()
            ->with('question')
            ->latest('updated_at')
            ->paginate(20);

        return view('admin.question_answers.index', compact('questionAnswers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.question_answers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreQuestionAnswerRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $questionAnswer = QuestionAnswer::create($this->shortTexts($request));

            $this->syncQuestionTree($questionAnswer, $request->validated('question'));
        });

        return redirect()->route('admin.question-answers.index')->with('status', 'Q&Aを登録しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(QuestionAnswer $questionAnswer): View
    {
        $questionTree = $questionAnswer->questionTree();

        return view('admin.question_answers.show', compact('questionAnswer', 'questionTree'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(QuestionAnswer $questionAnswer): View
    {
        $questionTree = $questionAnswer->questionTree();

        return view('admin.question_answers.edit', compact('questionAnswer', 'questionTree'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateQuestionAnswerRequest $request, QuestionAnswer $questionAnswer): RedirectResponse
    {
        DB::transaction(function () use ($request, $questionAnswer) {
            $questionAnswer->update($this->shortTexts($request));

            $this->syncQuestionTree($questionAnswer, $request->validated('question'));
        });

        return redirect()->route('admin.question-answers.index')->with('status', 'Q&Aを更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(QuestionAnswer $questionAnswer): RedirectResponse
    {
        DB::transaction(fn () => $questionAnswer->delete());

        return redirect()->route('admin.question-answers.index')->with('status', 'Q&Aを削除しました。');
    }

    /**
     * 簡易版の質問・回答の短文(分岐ありの形式では空にする)。
     *
     * @return array{short_question_text: ?string, short_answer_text: ?string}
     */
    private function shortTexts(FormRequest $request): array
    {
        $isSimple = $request->validated('type') === 'simple';

        return [
            'short_question_text' => $isSimple ? $request->validated('short_question_text') : null,
            'short_answer_text' => $isSimple ? $request->validated('short_answer_text') : null,
        ];
    }

    /**
     * フォームから送信された質問・回答の入れ子の内容に、Q&A 配下の質問・回答を同期する。
     * 簡易版(トップ表示用)の Q&A には質問・回答を登録しないため、すべて削除する。
     *
     * @param  array<string, mixed>|null  $questionData
     */
    private function syncQuestionTree(QuestionAnswer $questionAnswer, ?array $questionData): void
    {
        if ($questionAnswer->top_view || $questionData === null) {
            $questionAnswer->deleteQuestionTree();

            return;
        }

        $existing = $questionAnswer->questionTreeIds();
        $kept = ['questions' => [], 'answers' => []];

        $this->saveQuestion($questionData, $questionAnswer->id, $existing, $kept);

        $removedQuestionIds = array_values(array_diff($existing['questions'], $kept['questions']));
        $removedAnswerIds = array_values(array_diff($existing['answers'], $kept['answers']));

        BranchQuestionAnswer::query()
            ->where(fn ($query) => $query->whereIn('question_id', $removedQuestionIds)->orWhereIn('answer_id', $removedAnswerIds))
            ->delete();
        Answer::query()->whereIn('id', $removedAnswerIds)->delete();
        Question::query()->whereIn('id', $removedQuestionIds)->delete();

        $questionAnswer->unsetRelation('question');
    }

    /**
     * 質問 1 件とその回答を保存し、分岐先の質問があれば再帰的に保存する。
     * 送信された ID は、この Q&A 配下に既に存在するものだけを更新対象にする(それ以外は新規作成)。
     *
     * @param  array<string, mixed>  $data
     * @param  array{questions: list<int>, answers: list<int>}  $existing
     * @param  array{questions: list<int>, answers: list<int>}  $kept
     */
    private function saveQuestion(array $data, ?int $questionAnswerId, array $existing, array &$kept): Question
    {
        $question = $this->findReusable(Question::class, $data['id'] ?? null, $existing['questions'], $kept['questions']);
        $question->fill([
            'question_answer_id' => $questionAnswerId,
            'question_text' => $data['question_text'],
        ])->save();
        $kept['questions'][] = $question->id;

        foreach ($data['answers'] as $answerData) {
            $nextQuestion = isset($answerData['question'])
                ? $this->saveQuestion($answerData['question'], null, $existing, $kept)
                : null;

            $answer = $this->findReusable(Answer::class, $answerData['id'] ?? null, $existing['answers'], $kept['answers']);
            $answer->fill([
                'question_id' => $nextQuestion?->id,
                'answer_text' => $answerData['answer_text'] ?? null,
            ])->save();
            $kept['answers'][] = $answer->id;

            BranchQuestionAnswer::query()
                ->where('answer_id', $answer->id)
                ->where('question_id', '!=', $question->id)
                ->delete();
            BranchQuestionAnswer::query()->firstOrCreate([
                'question_id' => $question->id,
                'answer_id' => $answer->id,
            ]);
        }

        return $question;
    }

    /**
     * 既存の質問・回答を ID で取得する。この Q&A 配下にない ID や、同じリクエスト内で使用済みの ID なら新しいインスタンスを返す。
     *
     * @template TModel of Question|Answer
     *
     * @param  class-string<TModel>  $modelClass
     * @param  list<int>  $existingIds
     * @param  list<int>  $keptIds
     * @return TModel
     */
    private function findReusable(string $modelClass, mixed $id, array $existingIds, array $keptIds): Question|Answer
    {
        $id = filled($id) ? (int) $id : null;

        if ($id !== null && in_array($id, $existingIds, true) && ! in_array($id, $keptIds, true)) {
            return $modelClass::query()->findOrFail($id);
        }

        return new $modelClass;
    }
}
