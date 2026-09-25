<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Q&A(簡易版、または質問・回答の分岐)を入力するフォームリクエスト用の共通処理。
 */
trait ValidatesQuestionAnswer
{
    /**
     * 分岐の入れ子の上限(これより深い質問は受け付けない)。
     */
    private const MAX_QUESTION_DEPTH = 20;

    /**
     * 形式(type)が簡易版なら質問・回答の短文、分岐ありなら最初の質問からの入れ子をバリデーションする。
     *
     * @return array<string, array<mixed>>
     */
    protected function questionAnswerRules(): array
    {
        $rules = [
            'type' => ['required', Rule::in(['simple', 'branch'])],
        ];

        if ($this->input('type') === 'simple') {
            return $rules + [
                'short_question_text' => ['required', 'string', 'max:65535'],
                'short_answer_text' => ['required', 'string', 'max:65535'],
            ];
        }

        return $rules + ['question' => ['required', 'array']]
            + $this->questionNodeRules('question', $this->input('question'), 1);
    }

    /**
     * 項目名を、入れ子のキー(question.answers.0.answer_text など)の末尾から「質問文」「回答文」などにする。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $labels = [
            'short_question_text' => __('質問(簡易版)'),
            'short_answer_text' => __('回答(簡易版)'),
            'question_text' => __('質問文'),
            'answer_text' => __('回答文'),
            'answers' => __('回答'),
            'question' => __('質問'),
        ];

        return collect(array_keys($this->rules()))
            ->mapWithKeys(fn (string $key) => [$key => $labels[Str::afterLast($key, '.')] ?? null])
            ->filter()
            ->all();
    }

    /**
     * 質問 1 件とその回答(分岐先の質問を含む)のルールを、送信された入れ子の形に合わせて組み立てる。
     *
     * @return array<string, array<mixed>>
     */
    private function questionNodeRules(string $key, mixed $question, int $depth): array
    {
        if ($depth > self::MAX_QUESTION_DEPTH) {
            return [$key => ['prohibited']];
        }

        $rules = [
            "{$key}.id" => ['nullable', 'integer'],
            "{$key}.question_text" => ['required', 'string', 'max:65535'],
            "{$key}.answers" => ['required', 'array', 'min:1'],
        ];

        $answers = is_array($question) && is_array($question['answers'] ?? null) ? $question['answers'] : [];

        foreach ($answers as $index => $answer) {
            $answerKey = "{$key}.answers.{$index}";

            $rules["{$answerKey}.id"] = ['nullable', 'integer'];
            // 分岐しない回答は answer_text を表示して終わるため、分岐先の質問か回答文のどちらかを必須にする
            $rules["{$answerKey}.answer_text"] = ['nullable', 'string', 'max:65535', "required_without:{$answerKey}.question"];
            $rules["{$answerKey}.question"] = ['nullable', 'array'];

            if (is_array($answer) && isset($answer['question'])) {
                $rules += $this->questionNodeRules("{$answerKey}.question", $answer['question'], $depth + 1);
            }
        }

        return $rules;
    }
}
