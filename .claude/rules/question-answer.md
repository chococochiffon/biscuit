---
paths:
  - app/app/Models/QuestionAnswer.php
  - app/app/Models/Question.php
  - app/app/Models/Answer.php
  - app/app/Models/BranchQuestionAnswer.php
  - app/app/Http/Controllers/QuestionAnswerController.php
  - app/app/Http/Controllers/API/QuestionAnswerController.php
  - app/app/Http/Requests/*QuestionAnswerRequest.php
  - app/app/Http/Requests/Concerns/ValidatesQuestionAnswer.php
  - app/app/Http/Resources/QuestionAnswerResource.php
  - app/resources/views/admin/question_answers/**
  - app/database/factories/QuestionAnswerFactory.php
  - app/database/factories/QuestionFactory.php
  - app/database/factories/AnswerFactory.php
  - app/database/seeders/QuestionAnswerSeeder.php
  - app/tests/Feature/**/QuestionAnswer*
---

# Q&A 機能(QuestionAnswer)

管理画面は `admin.question-answers`。

## データ構造

- 親の `QuestionAnswer` は簡易版（`short_question_text`/`short_answer_text`）を両方入力すると保存時に `top_view` が true になり（トップ表示用）、その場合は質問・回答を登録しない。
- 分岐ありの場合は最初の `Question` だけが `question_answer_id` を持ち、質問の回答（選択肢）は中間テーブル `branch_question_answers`（`BranchQuestionAnswer`、論理削除付きの Pivot）で結ぶ。
- `Answer.question_id` は回答後に進む分岐先の質問で、null なら `answer_text` を表示して終わる（どちらか必須）。
- 木のたどり方は `QuestionAnswer::questionTreeIds()`/`questionTree()`。

## 管理画面での保存

入れ子のフォーム（`question[answers][n][question]...`）で木全体を一度に送信する。`ValidatesQuestionAnswer` が送信された形に合わせてルールを再帰的に組み立て、`QuestionAnswerController` がこの Q&A 配下の既存 ID だけを更新対象にして同期する（送られなかった質問・回答は論理削除）。

## API

`GET /api/question-answers`（`API\QuestionAnswerController@index`）は登録順の一覧で、`top_view` で簡易版/分岐ありを絞り込み、分岐ありは `QuestionAnswer::questionTree()` の入れ子を `question` に含める。
