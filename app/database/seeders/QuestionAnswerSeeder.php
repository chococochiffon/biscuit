<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\BranchQuestionAnswer;
use App\Models\Question;
use App\Models\QuestionAnswer;
use Illuminate\Database\Seeder;

class QuestionAnswerSeeder extends Seeder
{
    /**
     * サンプルの Q&A(簡易版と分岐あり)を登録する。同じ質問が登録済みならスキップする。
     */
    public function run(): void
    {
        $simpleQuestionAnswers = [
            ['結局このサイトってなんなの?', 'ポートフォリオみたいなものだとおもって!'],
            ['ブログでやれば?', 'やだ!'],
            ['もっと詳しい DEMO がみたいんだけど?', 'X (@chococo_chiffon) に DM くれ!'],
        ];

        foreach ($simpleQuestionAnswers as [$shortQuestionText, $shortAnswerText]) {
            if (QuestionAnswer::query()->where('short_question_text', $shortQuestionText)->exists()) {
                continue;
            }

            // DatabaseSeeder はモデルイベントを止めて実行するため、保存時の top_view の自動設定に頼らず明示する
            QuestionAnswer::query()->make([
                'short_question_text' => $shortQuestionText,
                'short_answer_text' => $shortAnswerText,
            ])->forceFill(['top_view' => true])->save();
        }

        $branchingQuestions = [
            [
                'question_text' => 'このサイトの何が知りたい?',
                'answers' => [
                    ['answer_text' => '作り方', 'question' => [
                        'question_text' => 'どちら側のこと?',
                        'answers' => [
                            ['answer_text' => '管理画面と API は Laravel で作っています。'],
                            ['answer_text' => '公開側は Nuxt 4 で作っていて、API からデータを取得して表示しています。'],
                        ],
                    ]],
                    ['answer_text' => '中の人', 'question' => [
                        'question_text' => '何を聞きたい?',
                        'answers' => [
                            ['answer_text' => 'スキルはトップページのスキルリストを見てね!'],
                            ['answer_text' => '連絡は X (@chococo_chiffon) の DM へどうぞ!'],
                        ],
                    ]],
                ],
            ],
            [
                'question_text' => 'お問い合わせの内容は?',
                'answers' => [
                    ['answer_text' => 'お仕事のご依頼', 'question' => [
                        'question_text' => 'ご依頼の種類は?',
                        'answers' => [
                            ['answer_text' => 'Web サイト制作は X の DM で内容をお知らせください。'],
                            ['answer_text' => '記事の執筆は X の DM で内容をお知らせください。'],
                        ],
                    ]],
                    ['answer_text' => 'サイトの不具合は X (@chococo_chiffon) に DM でお知らせください!'],
                ],
            ],
        ];

        foreach ($branchingQuestions as $questionData) {
            $alreadySeeded = Question::query()
                ->whereNotNull('question_answer_id')
                ->where('question_text', $questionData['question_text'])
                ->exists();

            if ($alreadySeeded) {
                continue;
            }

            $questionAnswer = QuestionAnswer::query()->make()->forceFill(['top_view' => false]);
            $questionAnswer->save();

            $this->createQuestion($questionData, $questionAnswer->id);
        }
    }

    /**
     * 質問とその回答を登録し、分岐先の質問があれば再帰的に登録する。
     *
     * @param  array{question_text: string, answers: list<array{answer_text: string, question?: array<string, mixed>}>}  $questionData
     */
    private function createQuestion(array $questionData, ?int $questionAnswerId): Question
    {
        $question = Question::query()->create([
            'question_answer_id' => $questionAnswerId,
            'question_text' => $questionData['question_text'],
        ]);

        foreach ($questionData['answers'] as $answerData) {
            $nextQuestion = isset($answerData['question'])
                ? $this->createQuestion($answerData['question'], null)
                : null;

            $answer = Answer::query()->create([
                'question_id' => $nextQuestion?->id,
                'answer_text' => $answerData['answer_text'],
            ]);

            BranchQuestionAnswer::query()->create([
                'question_id' => $question->id,
                'answer_id' => $answer->id,
            ]);
        }

        return $question;
    }
}
