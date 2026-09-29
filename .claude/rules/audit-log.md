---
paths:
  - app/app/Support/AuditLogger.php
  - app/app/Support/SyncedRows.php
  - app/app/Models/AuditLog.php
  - app/app/Enums/AuditAction.php
  - app/app/Listeners/RecordAdministratorAuthentication.php
  - app/app/Http/Controllers/AuditLogController.php
  - app/app/Http/Controllers/Concerns/SyncsSortableRows.php
  - app/resources/views/admin/audit_logs/**
  - app/tests/Feature/AuditLog*Test.php
---

# 監査ログ（AuditLog）

管理画面の操作を「誰が・いつ・何に・何をしたか」として `audit_logs` に残す。管理画面の書き込み系の操作（コントローラーのアクション）を追加・変更するときは、必ず記録を入れること（`AuditLogCoverageTest` が、`admin` から始まる POST・PUT・PATCH・DELETE のルートのアクションのソースに `AuditLogger::` があるかを確認する。ログイン・ログアウトの `AdministratorSessionController` だけ対象外）。

## 記録のしかた

- 記録は `Support\AuditLogger` を使う。操作者はログイン中の管理者（`admin` ガード）で、IP アドレス・User-Agent・ルート名はリクエストから取る。
- モデルイベントには頼らず、コントローラーの操作ごとに 1 件を明示的に記録する（`syncSortableRows()`・一括更新・並び替えなどクエリで書き込む処理はモデルイベントを通らないため）。
- 保存処理と同じトランザクションの中で記録し、取り消した操作はログにも残さない。単純な登録・更新・削除は `createWithLog()`/`updateWithLog()`/`deleteWithLog()`（トランザクションで囲んで保存と記録をする）を使う。すでにトランザクションがある処理では、その中で `snapshot()`（更新前の値）→ 保存 → `created()`/`updated()`/`deleted()` の順に呼ぶ。
- `changes` は本体の列の差分（項目名 → [変更前, 変更後]。登録は [null, 値]、削除は [値, null]）。`id`・日時・`password`・`remember_token`・モデルの `$hidden`・`unique_` で始まる生成カラムは残さず、`AuditLogger::MAX_VALUE_LENGTH`（1000 文字）を超える値は先頭だけを残す。本体の列以外に比べたい値（記事のタグ `tags`、ユーザー詳細 `detail.*`、カスタムフォームの入力値 `field.項目名`）は `snapshot()`/`created()`/`updated()` の `$extra` に渡す。パスワードは値を残さず `metadata.password_changed` だけを残す。
- `metadata` は補足。入れ子の行は `syncSortableRows()` の戻り値 `SyncedRows::summary()`（作成・更新・削除の件数）を種類ごとに残す。並び替えは操作 1 回につき 1 件（`order` に id の並び）、記事の一括公開設定は記事ごとに 1 件（`bulk_count`）残す。
- 対象（`subject_type`）はモデル名のスネークケース（例: `article`）で、カスタムページは `custom_page:種類の名前`。モデルのない対象は文字列で渡す（例: `layout`・`single_page` の並び替え・`article_content_image`）。表示名は `AuditLog::labelForSubjectType()` に足す。操作者・対象の名前は記録時点の値を残す（あとで削除・改名されても読める）。
- 管理画面のログイン・ログアウト・ログイン失敗は、認証イベントのリスナー `Listeners\RecordAdministratorAuthentication`（`app/Listeners` に置いた `handle` で始まるメソッドは自動で登録される）が `admin` ガードに限って記録する。ログイン失敗は入力されたメールアドレスだけを残す。

## テーブル・閲覧

- `audit_logs` は追記するだけで変更・削除しない（モデルの `updating`/`deleting` で例外を投げる）。そのため全モデル論理削除の方針の例外として `deleted_at`（と `updated_at`）を持たない。保存期間は当面無期限。
- 閲覧は管理画面の「操作ログ」（`admin.audit-logs.index`/`show`、サイドメニューの「システム」）で、スーパー管理者だけが使える（ゲート `view-audit-logs`）。一覧は期間・操作者・対象の種類・対象の id・操作で絞り込み（不正な値は `Validator::valid()` で無視）、詳細は変更前・変更後と補足を表示する。詳細の「この対象の履歴」から、同じ対象のログだけに絞り込める。
