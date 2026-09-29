---
paths:
  - app/app/Http/Controllers/API/AuthController.php
  - app/app/Http/Controllers/API/MeController.php
  - app/app/Http/Controllers/API/MyArticleController.php
  - app/app/Http/Controllers/API/AuthorController.php
  - app/app/Http/Controllers/API/PasswordResetController.php
  - app/app/Notifications/ResetPasswordNotification.php
  - app/resources/views/mail/reset-password.blade.php
  - app/tests/Feature/API/PasswordResetControllerTest.php
  - app/app/Http/Resources/AuthorResource.php
  - app/tests/Feature/API/AuthorControllerTest.php
  - app/app/Http/Controllers/ArticlePathOptionController.php
  - app/app/Http/Controllers/Concerns/SavesArticle.php
  - app/app/Models/ArticlePathOption.php
  - app/app/Http/Controllers/Concerns/SavesUserProfile.php
  - app/app/Http/Requests/API/**
  - app/app/Http/Resources/MeResource.php
  - app/app/Http/Resources/MyArticleResource.php
  - app/app/Models/User.php
  - app/config/sanctum.php
  - app/tests/Feature/API/AuthControllerTest.php
  - app/tests/Feature/API/MeControllerTest.php
  - app/tests/Feature/API/MyArticleControllerTest.php
---

# ユーザーの API（chococo のマイページ）

chococo のマイページ（ログイン・プロフィール・アイコン画像・パスワードの変更と、記事の投稿・承認の申請）のための API。ログインできるのは管理画面で登録したユーザーだけで、登録の API は持たない。

## 認証（Laravel Sanctum の API トークン）

- 本番の chococo と biscuit は完全に別ドメインのため、Cookie・セッションによる SPA 認証は使わず、API トークンだけで認証する（`config/sanctum.php` の `stateful`・`guard` は空）。
- `POST /api/auth/login`（`API\AuthController`。回数制限 `throttle:user-login`）でトークンを発行し、`token`・`expires_at`・`user` を返す。有効期限は `SANCTUM_TOKEN_EXPIRATION`（分、既定 30 日）。
- ログインが必要な API は `auth:sanctum` のグループに置き、`Authorization: Bearer {token}` で呼ぶ（未ログイン・無効・期限切れは 401）。論理削除したユーザーはログインできず、発行済みのトークンも使えない。
- トークンは削除しない（物理削除しない方針のため）。ログアウト・パスワード変更（ほかの端末のトークン）は `expires_at` を過去にして無効にする（`User::expireTokens()`）。`sanctum:prune-expired` は使わない。そのため `personal_access_tokens` は `deleted_at` を持たない。

## マイページの API（`API\MeController`）

- `GET /api/me`: ログイン中のユーザー（`MeResource`。プロフィールの編集に使う入力値をそのまま返す）。
- `PUT /api/me/profile`: 名前・メールアドレス・ユーザー詳細・スキル。検証は管理画面のユーザー編集と同じ `StoreUserRequest` を継承した `UpdateMyProfileRequest`（対象はログイン中のユーザー。`targetUser()`）で、保存は管理画面と共通のトレイト `Http\Controllers\Concerns\SavesUserProfile`。
- `POST /api/me/profile/image`: アイコン画像（multipart。`crop[x]` などの切り抜き範囲は任意で、未指定なら中央で切り抜く）。
- `PUT /api/me/password`: 今のパスワード（`current_password:sanctum`）が必要。変更すると使っているトークン以外を無効にする。
- 操作は監査ログに操作者 `user` として残す（`AuditLogger` は admin ガードにいなければ sanctum ガードのユーザーを操作者にする）。ログイン失敗は対象の種類 `user` で、入力されたメールアドレスだけを残す。

## 記事の投稿と承認の申請（`API\MyArticleController`）

- `/api/me/articles/**` に置く（chococo の BFF の `/api/me/**` の中継をそのまま使える）。記事は `AppServiceProvider` の `myArticle` のバインド（`Route::bind`）でログイン中のユーザーの記事だけを取り出し、ほかのユーザーの記事は 404 にする。
- 公開ステータスは API から直接変えさせない。作成は必ず下書き（`draft`）で、`POST /me/articles/{id}/submit`（下書き → 承認待ち。差し戻しの理由 `review_comment` を消す）・`withdraw`（承認待ち → 下書き）で変える。公開（`published`）にできるのは管理者だけ。
- 公開中の記事を更新（本文・サムネイル画像の `POST /me/articles/{id}/thumbnail`）すると承認待ちに戻り、承認されるまで公開側に出ない。削除（論理削除）は公開中でもできる。
- URL の親パスは入力させず、管理者が記事一覧の「投稿先管理」モーダル（`ArticlePathOption`、`admin.article-path-options.*`、上限 `limits.article_path_options`）で登録した投稿先から選ぶ（`GET /me/article-paths`、`article_path_option_id`）。記事には投稿先の `parent_path` の文字列を保存するため、投稿先を変更・削除しても既存の記事の URL は変わらない。更新で投稿先を送らなければ今の親パスのまま。スラッグはユーザーが入力する（任意、未入力なら記事番号）。
- 公開期間は入力させない。管理者が初めて公開にしたとき（`Article::changeApproval()`、`first_published_at` が空のとき）に公開開始日時を承認した日時にする（未来の日時にしてあれば予約公開としてそのまま）。再承認では最初に公開した日時を残す。
- 本文は保存前に `HtmlSanitizer::cleanArticle()` で無害化する（見出しと、本文用にアップロードした画像 `Article::contentImageUrlPrefix()` 以外の画像は取り除く）。本文の画像は `POST /me/articles/content-images`、タグの候補は `GET /me/tags?q=`（`Tag::suggest()`）。画像のアップロードは `throttle:user-uploads`（ユーザーごと）。
- 保存処理（タグの同期・監査ログのタグ）は管理画面の `ArticleController` と共通のトレイト `Http\Controllers\Concerns\SavesArticle`。
- 管理画面では承認待ちの件数をサイドメニューと記事一覧に出し（`View\Composers\PendingArticleComposer`）、ユーザーの記事の編集画面で差し戻しの理由を入力できる。

## パスワード再設定（`API\PasswordResetController`）

- ログイン前に使う。`POST /api/auth/forgot-password`（メールアドレス）で再設定のメールを送り、`POST /api/auth/reset-password`（`token`・`email`・`password`・`password_confirmation`）でパスワードを変える。どちらも回数制限 `throttle:user-password-reset`（メールアドレスと接続元ごと）。
- Laravel のパスワードブローカー（`password_reset_tokens`。有効期限 60 分、同じメールアドレスへの再送は 60 秒あける）を使う。トークンは使い捨てで、ブローカーが物理削除する（論理削除の方針の例外）。
- 登録の有無を知られないよう、`forgot-password` は登録がない・論理削除した・再送の間隔内のメールアドレスでも同じ応答を返す。
- メールは `ResetPasswordNotification`（`mail/reset-password.blade.php`。件名・差出人名・ヘッダーはサイト名）で、キューを使わずその場で送る（Docker にキューのワーカーがないため）。リンク先は公開側の `{サイト設定の front_url}/reset-password?token=…&email=…`（未登録なら `config('app.front_url')`、`.env` の `FRONT_URL`）。
- 再設定すると、発行済みの API トークン（ほかの端末を含むすべてのログイン）を無効にする（`User::expireTokens()`）。自動ではログインさせない。
- 監査ログは、依頼を `password_reset_requested`（入力されたメールアドレスだけ）、再設定を `password_reset`（操作者 `user`、パスワードの値は残さない）で残す。
- chococo は `/forgot-password`・`/reset-password` のページと、トークンなしで中継する `server/api/auth/forgot-password.post.ts`・`reset-password.post.ts`（`postToBiscuitAsGuest()`）を持つ。

## 投稿者ページ（`API\AuthorController`）

- 公開側（chococo）の `/authors/{ユーザーの id}` で、投稿者のプロフィールとその人の公開中の記事を表示する。ログインは不要。
- `GET /api/authors/{id}`（`AuthorResource`）は表示名・アイコン画像・コメント・スキルを返す。アカウント名・メールアドレス・誕生日は返さない。表示名は `User::authorName()`（ユーザー詳細の名前の表示設定に従い、非表示・未登録なら「投稿者」）。
- 公開するのは、ユーザー詳細の「プロフィールを公開する」（`view_flag`）がオンで論理削除されていないユーザーだけ（`User::hasPublicProfile()`）。それ以外は 404。
- 投稿者の記事は記事一覧 API の `GET /api/articles?author={id}` で取る。
- 記事・固定ページ・カスタムページの URL の先頭に `authors` は使えない（`Rules\NotReservedPath`、`CustomPageType::RESERVED_PATHS`）。

## chococo 側（BFF）

chococo のサーバー（Nitro の `server/`）がトークンを HttpOnly の Cookie（`chococo_token`）に入れて持ち、ブラウザには返さない。`/api/auth/login`・`/api/auth/logout` と、`/api/me/**` を biscuit へ中継する（`server/utils/biscuit.ts` の `proxyToBiscuit()`）。変更系のリクエストは Origin が公開側サイトの URL と一致するかを確かめる。ヘッダーのマイページへのリンクはログイン中のときだけ出す（`useMe().ensureMe()` でログイン状態を一度だけ確かめる。Cookie がなければ chococo のサーバーが biscuit へ問い合わせずに 401 を返す）。biscuit から見た接続元は chococo のサーバーになるため、監査ログの IP アドレス・ログインの回数制限はその IP で扱われる。
