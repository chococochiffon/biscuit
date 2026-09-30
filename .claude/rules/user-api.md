---
paths:
  - app/app/Http/Controllers/API/AuthController.php
  - app/app/Models/LoginCode.php
  - app/app/Notifications/LoginCodeNotification.php
  - app/resources/views/mail/login-code.blade.php
  - app/app/Http/Controllers/API/MeController.php
  - app/app/Http/Controllers/API/MyArticleController.php
  - app/app/Http/Controllers/API/MyGalleryImageController.php
  - app/app/Http/Controllers/Concerns/HandlesUserApproval.php
  - app/app/Http/Resources/MyGalleryImageResource.php
  - app/tests/Feature/API/MyGalleryImageControllerTest.php
  - app/app/Http/Controllers/API/AuthorController.php
  - app/app/Http/Controllers/API/PasswordResetController.php
  - app/app/Http/Controllers/API/InvitationController.php
  - app/app/Http/Controllers/UserInvitationController.php
  - app/app/Models/UserInvitation.php
  - app/app/Notifications/UserInvitationNotification.php
  - app/resources/views/mail/invitation.blade.php
  - app/tests/Feature/API/InvitationControllerTest.php
  - app/tests/Feature/UserInvitationControllerTest.php
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

chococo のマイページ（ログイン・プロフィール・アイコン画像・パスワードの変更と、記事・ギャラリーの画像の投稿・承認の申請）のための API。ログインできるのは管理画面で登録したユーザーと、管理者に招待されてプロフィールとパスワードを登録したユーザーだけで、自分で登録する API は持たない。

## 認証（Laravel Sanctum の API トークン）

- 本番の chococo と biscuit は完全に別ドメインのため、Cookie・セッションによる SPA 認証は使わず、API トークンだけで認証する（`config/sanctum.php` の `stateful`・`guard` は空）。
- ログインは二段階認証（メールの確認コード）。`POST /api/auth/login`（`API\AuthController`。回数制限 `throttle:user-login`）はメールアドレスとパスワードを確かめて 6 桁の確認コードをメールで送り（`LoginCode::issue()`・`LoginCodeNotification`）、`two_factor`・`challenge` を返す。`POST /api/auth/login/verify`（`challenge`・`code`）でコードを確かめると API トークンを発行し、`token`・`expires_at`・`user` を返す（`AuthController::tokenResponse()`）。`POST /api/auth/login/resend`（`challenge`）でコードを送り直すと新しいチャレンジを返し、古いコードは使えなくなる。コードの入力・再送は `throttle:login-code`（接続元ごと）。トークンの有効期限は `SANCTUM_TOKEN_EXPIRATION`（分、既定 30 日）。
- 確認コード（`login_codes`、`LoginCode`）は管理画面のログインと共通。コード（bcrypt）とチャレンジ（SHA-256）はハッシュで持ち、有効期限は `LOGIN_CODE_EXPIRE_MINUTES`（既定 10 分）、`config('auth.login_codes.max_attempts')`（5 回）間違えると使えなくなる。使った・送り直した・期限切れのコードは削除しない。管理画面はチャレンジをセッションに持ち（`/admin/login` → `/admin/login/verify` の 2 画面、`Auth\AdministratorSessionController`）、chococo はサーバーの HttpOnly の Cookie（`chococo_login_challenge`）に持つ。監査ログは確認コードの送信を `login_code_sent`、コードの間違いをログイン失敗（metadata `reason: login_code`）で残す。招待の受諾はメールのリンクで本人確認が済むため、コードなしでログインさせる。
- ログインが必要な API は `auth:sanctum` のグループに置き、`Authorization: Bearer {token}` で呼ぶ（未ログイン・無効・期限切れは 401）。論理削除したユーザーはログインできず、発行済みのトークンも使えない。
- トークンは削除しない（物理削除しない方針のため）。ログアウト・パスワード変更（ほかの端末のトークン）は `expires_at` を過去にして無効にする（`User::expireTokens()`）。`sanctum:prune-expired` は使わない。そのため `personal_access_tokens` は `deleted_at` を持たない。

## マイページの API（`API\MeController`）

- `GET /api/me`: ログイン中のユーザー（`MeResource`。プロフィールの編集に使う入力値をそのまま返す）。
- `PUT /api/me/profile`: アカウント名（`name`）・メールアドレス・ユーザー詳細・スキル。検証は管理画面のユーザー編集と同じ `StoreUserRequest` を継承した `UpdateMyProfileRequest`（対象はログイン中のユーザー。`targetUser()`）で、保存は管理画面と共通のトレイト `Http\Controllers\Concerns\SavesUserProfile`。
- `POST /api/me/profile/image`: アイコン画像（multipart。`crop[x]` などの切り抜き範囲は任意で、未指定なら中央で切り抜く）。
- `PUT /api/me/password`: 今のパスワード（`current_password:sanctum`）が必要。変更すると使っているトークン以外を無効にする。
- `GET /api/me/dashboard`（`API\MyDashboardController`・`Services\MyDashboardService`）: マイページのダッシュボード。管理画面のダッシュボードの項目をログイン中のユーザー本人の分に絞って返す（記事・ギャラリーの状態ごとの件数、最近編集した記事・画像（`status` は `Enums\ContentStatus` の値）、今日と 7 日以内の予約公開の記事、注意事項（差し戻し `returned`・サムネイル未設定 `no_thumbnail`・承認待ち `pending`）、自分の最近の操作（ログイン・ログアウト・確認コードの送信は除く。IP アドレスは返さない）、アカウント（承認を飛ばす権限・投稿者ページ・最近のログイン）、自分がアップロードした画像の件数・容量（記事本文の画像は投稿者を持たないため数えない））。サイト全体やほかのユーザーの数字は返さない。アクセスは `GET /api/me/page-views`（`page-views.md`）。chococo は `pages/mypage/index.vue` で両方を取得して表示する。
- 操作は監査ログに操作者 `user` として残す（`AuditLogger` は admin ガードにいなければ sanctum ガードのユーザーを操作者にする）。ログイン失敗は対象の種類 `user` で、入力されたメールアドレスだけを残す。

## 記事の投稿と承認の申請（`API\MyArticleController`）

- `/api/me/articles/**` に置く（chococo の BFF の `/api/me/**` の中継をそのまま使える）。記事は `AppServiceProvider` の `myArticle` のバインド（`Route::bind`）でログイン中のユーザーの記事だけを取り出し、ほかのユーザーの記事は 404 にする。
- 公開ステータスは API から直接変えさせない。作成は必ず下書き（`draft`）で、`POST /me/articles/{id}/submit`（下書き → 承認待ち。差し戻しの理由 `review_comment` を消す）・`withdraw`（承認待ち → 下書き）で変える。公開（`published`）にできるのは管理者だけ。
- 公開中の記事を更新（本文・サムネイル画像の `POST /me/articles/{id}/thumbnail`）すると承認待ちに戻り、承認されるまで公開側に出ない。削除（論理削除）は公開中でもできる。
- 承認を飛ばす権限（`users.skip_approval`。既定は false で承認が必要）は管理画面のユーザー登録・編集でだけ設定する（マイページのプロフィール更新 `UpdateMyProfileRequest` からは外している）。権限のあるユーザーは、`submit` で承認待ちを経ずにそのまま公開になり（`Article::changeApproval()` で管理者の承認と同じく公開開始日時も決まる）、公開中の記事を更新しても公開中のまま。`GET /api/me` の `skip_approval` で chococo がボタンの文言などを出し分ける。ギャラリーの画像にも同じ権限を使う。申請・取り下げ・公開中の変更で承認待ちに戻す処理は、記事とギャラリーで共通のトレイト `Http\Controllers\Concerns\HandlesUserApproval`（`approvalOnSubmit()`・`backToPendingIfPublished()`・`ensureApproval()`）にまとめている。
- URL の親パスは入力させず、管理者が記事一覧の「投稿先管理」モーダル（`ArticlePathOption`、`admin.article-path-options.*`、上限 `limits.article_path_options`）で登録した投稿先から選ぶ（`GET /me/article-paths`、`article_path_option_id`）。記事には投稿先の `parent_path` の文字列を保存するため、投稿先を変更・削除しても既存の記事の URL は変わらない。更新で投稿先を送らなければ今の親パスのまま。スラッグはユーザーが入力する（任意、未入力なら記事番号）。
- 公開期間は入力させない。管理者が初めて公開にしたとき（`Article::changeApproval()`、`first_published_at` が空のとき）に公開開始日時を承認した日時にする（未来の日時にしてあれば予約公開としてそのまま）。再承認では最初に公開した日時を残す。
- 本文は保存前に `HtmlSanitizer::cleanArticle()` で無害化する（見出しと、本文用にアップロードした画像 `Article::contentImageUrlPrefix()` 以外の画像は取り除く）。本文の画像は `POST /me/articles/content-images`、タグの候補は `GET /me/tags?q=`（`Tag::suggest()`）。画像のアップロードは `throttle:user-uploads`（ユーザーごと）。
- 保存処理（タグの同期・監査ログのタグ）は管理画面の `ArticleController` と共通のトレイト `Http\Controllers\Concerns\SavesArticle`。
- 管理画面では承認待ちの件数をサイドメニューと記事一覧に出し（`View\Composers\PendingArticleComposer`）、ユーザーの記事の編集画面で差し戻しの理由を入力できる。

## ギャラリーの画像の投稿と承認の申請（`API\MyGalleryImageController`）

- `/api/me/gallery-images/**` に置き、流れは記事と同じ（下書きで作成 → `submit` で承認待ち → 管理者が公開。`withdraw` で下書きに戻す。公開中の画像を変更すると承認待ちに戻る）。画像は `myGalleryImage` のバインドでログイン中のユーザーのものだけを取り出す。
- `gallery_images` は投稿したユーザー `user_id`（null なら管理者の投稿）・公開ステータス `approval`（記事と同じ `ArticleApprovalStatus`。既定値は `published`）・差し戻しの理由 `review_comment` を持つ。管理画面からの登録は承認なしで公開にする。公開側（`GET /api/gallery-images`・呼び出しコンテンツ）は `GalleryImage::published()` で公開中だけを出す。
- 登録（`POST /me/gallery-images`、multipart の `image`・`name`・`gallery_category_id`・`comment`）は並び順を末尾にする。項目の更新は `PUT`、画像ファイルの変更は `POST /me/gallery-images/{id}/image`。登録と画像の変更は `throttle:user-uploads`。分類の選択肢は公開側の `GET /api/gallery-categories` を使う。
- 管理画面のギャラリー一覧はステータス・投稿者の列とステータスの絞り込みを持ち、承認待ちの件数をサイドメニューと一覧に出す（`View\Composers\PendingGalleryImageComposer`）。編集画面で公開ステータスを変え、ユーザーの画像には差し戻しの理由を入力できる。

## 管理者からの招待（`UserInvitationController`・`API\InvitationController`）

- 管理画面のユーザー一覧の「招待」（`admin.users.invite`）でメールアドレスと承認を飛ばす権限を入れると、アカウント名をメールアドレスの @ の前（`User::accountNameFromEmail()`）にした無効なユーザー（`users.active_flag` = false。パスワードは誰も知らないランダムな値）を作り、招待のメール（`UserInvitationNotification`・`mail/invitation.blade.php`）を送る。管理画面の「新規登録」でパスワードまで入れたユーザーは最初から有効。
- 招待は `user_invitations`（`UserInvitation`。トークンは SHA-256 のハッシュ・有効期限 `expires_at`・受諾日時 `accepted_at`・招待した管理者）。有効期限は `config('auth.invitations.expire')`（分、`.env` の `INVITATION_EXPIRE_MINUTES`、既定 24 時間）。期限が切れたら、管理者がユーザー一覧の「招待を再送」（`admin.users.invitation.resend`、無効なユーザーだけ）で送り直す。再送すると古い招待は削除せず、有効期限を切らして無効にする（`UserInvitation::issue()`）。ユーザー一覧の「状態」は有効・招待中・招待の期限切れ。
- メールのリンクは公開側の `{front_url}/invitation?token=…&email=…`（`SiteSetting::frontUrl()`）。`GET /api/auth/invitation`（リンクが使えるかと、アカウント名の初期値）・`POST /api/auth/invitation`（アカウント名・パスワード・ユーザー詳細・スキル。`AcceptInvitationRequest` は `StoreUserRequest` を継承し、メールアドレスと承認を飛ばす権限は変えられない）はログイン前の API で、`throttle:user-invitation`（接続元ごと）。受諾するとユーザーを有効にし、ログインと同じ応答（`AuthController::tokenResponse()`）でトークンを返す。リンクが無効・期限切れ・受諾済みなら `GET` は 404、`POST` は `token` の入力エラー。
- 無効なユーザーはログインできず（登録がないときと同じ応答）、パスワード再設定のメールも送らない。
- 監査ログは、招待を `created`（metadata `invited`）、再送を `invited`、受諾を `invitation_accepted`（操作者は招待されたユーザー）で残す。
- chococo は `/invitation` のページと、`server/api/auth/invitation.get.ts`（中継）・`invitation.post.ts`（ログインと同じくトークンを Cookie に入れる）を持つ。

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

chococo のサーバー（Nitro の `server/`）がトークンを HttpOnly の Cookie（`chococo_token`）に入れて持ち、ブラウザには返さない。`/api/auth/login`・`/api/auth/login-verify`・`/api/auth/login-resend`（確認コード。チャレンジは Cookie `chococo_login_challenge` に持つ）・`/api/auth/logout` と、`/api/me/**` を biscuit へ中継する（`server/utils/biscuit.ts` の `proxyToBiscuit()`）。変更系のリクエストは Origin が公開側サイトの URL と一致するかを確かめる。ヘッダーのマイページへのリンクはログイン中のときだけ出す（`useMe().ensureMe()` でログイン状態を一度だけ確かめる。Cookie がなければ chococo のサーバーが biscuit へ問い合わせずに 401 を返す）。biscuit から見た接続元は chococo のサーバーになるため、監査ログの IP アドレス・ログインの回数制限はその IP で扱われる。
