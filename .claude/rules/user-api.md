---
paths:
  - app/app/Http/Controllers/API/AuthController.php
  - app/app/Http/Controllers/API/MeController.php
  - app/app/Http/Controllers/Concerns/SavesUserProfile.php
  - app/app/Http/Requests/API/**
  - app/app/Http/Resources/MeResource.php
  - app/app/Models/User.php
  - app/config/sanctum.php
  - app/tests/Feature/API/AuthControllerTest.php
  - app/tests/Feature/API/MeControllerTest.php
---

# ユーザーの API（chococo のマイページ）

chococo のマイページ（ログイン・プロフィール・アイコン画像・パスワードの変更）のための API。ログインできるのは管理画面で登録したユーザーだけで、登録の API は持たない。

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

## chococo 側（BFF）

chococo のサーバー（Nitro の `server/`）がトークンを HttpOnly の Cookie（`chococo_token`）に入れて持ち、ブラウザには返さない。`/api/auth/login`・`/api/auth/logout` と、`/api/me/**` を biscuit へ中継する（`server/utils/biscuit.ts` の `proxyToBiscuit()`）。変更系のリクエストは Origin が公開側サイトの URL と一致するかを確かめる。ヘッダーのマイページへのリンクはログイン中のときだけ出す（`useMe().ensureMe()` でログイン状態を一度だけ確かめる。Cookie がなければ chococo のサーバーが biscuit へ問い合わせずに 401 を返す）。biscuit から見た接続元は chococo のサーバーになるため、監査ログの IP アドレス・ログインの回数制限はその IP で扱われる。
