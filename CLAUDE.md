# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## リポジトリ構成

このリポジトリは Laravel アプリケーションを内包するトップレベルのプロジェクトコンテナです。Laravel アプリ本体は `app/` 配下にすべて存在します（`app/app`、`app/routes`、`app/database` など。Laravel 自身が持つ内側の `app/` ディレクトリと紛らわしいので注意）。リポジトリルートの `docker/` と `docker-compose.yml` はローカル開発環境の定義です。`composer`/`artisan`/`npm` コマンドはすべて `app/` の中で実行してください。

「biscuit」は記事投稿型サイト（ブログ）の管理画面と公開側を持つアプリケーションとして作り込まれている最中です。デフォルトの `User` モデル、管理者機能（`Administrator` モデル、`admin` 認証ガード、`/admin` 配下の認証・CRUD 一式）に加え、記事（`Article`）・タグ（`Tag`）・固定ページ（`SinglePage`/`SinglePageDetail`）・サイト設定（`SiteSetting`）・呼び出しコンテンツ（`CallContent`/`ContentModelRelation`）・ユーザー詳細（`UserDetail`）とそのスキル（`UserSkill`）・SNS リンク（`SocialLink`）・トップスライダー画像（`TopSliderImage`）・Q&A（`QuestionAnswer`/`Question`/`Answer`）のドメインモデルとその管理画面 CRUD が実装されている。

## 環境構築

ローカル開発は Docker Compose 経由で動かします（リポジトリルートで実行）。

```
docker compose up -d
```

起動するコンテナ（`app`/`nginx`/`db`/`phpmyadmin`）の詳細は `docker-compose.yml` を参照。

ローカルの `.env`（デフォルト）は Dockerized MySQL ではなく SQLite（`DB_CONNECTION=sqlite`）を使う設定になっています。MySQL コンテナを使いたい場合は `DB_*` 系の環境変数を `db` サービス向けに書き換えてください。

初回セットアップは `app/` の中で標準的な Laravel の手順（`composer install`、`.env` 作成、`key:generate`、`migrate` など）を行います。`composer run setup` は install・`.env` コピー・キー生成・migrate・npm install/build の大部分を一括で行います。アップロード画像（`public` ディスク）の表示には `php artisan storage:link` も必要です。既存の `public/storage` はコンテナ内の絶対パス（`/var/www/app/storage/app/public`）を指すシンボリックリンクなので、コンテナ内（`docker compose exec app php artisan storage:link`）で作成してください（ホスト側からはリンク切れに見えますが正常です）。

`docker compose exec app <command>` で `artisan`/`pint` などをコンテナ内から実行できますが、コンテナ内プロセスは root で動くため、`make:*` などファイルを生成するコマンドを叩くとホスト側で root 所有のファイルが作られます。編集できない場合は `docker compose exec -u root app chown -R $(id -u):$(id -g) <path>` で所有権をホストユーザーに戻してください。同様に `docker compose exec app php artisan test` などでビューがコンパイルされると `storage/framework/views`（や `bootstrap/cache`）に root 所有のファイルができ、php-fpm（www-data）が更新できずブラウザ表示時に `touch(): Utime failed: Operation not permitted`（`BladeCompiler.php`）になる。コンテナ内でテスト等を実行した後は `docker compose exec -u root app chown -R www-data:www-data storage/framework bootstrap/cache` で所有者を戻すこと。また `docker compose exec` 経由の git はリポジトリルートの `.git` を見つけられない（`app/` だけがマウントされているため）ので、`--dirty` を使う Pint はホスト側の git か、対象ファイルを明示指定して実行する必要があります。

## よく使うコマンド

すべて `app/` の中で実行します。

- `php artisan dev`（または `composer dev`）— アプリ本体・キューリスナー・Vite dev サーバーを同時起動。
- `php artisan test`（または `composer test`）— テストスイート全体を実行（実行前に config をクリアする）。特定のテストのみ実行する場合は `php artisan test --filter=testName`、ファイル指定は `php artisan test tests/Feature/ExampleTest.php`。`vendor/bin/phpunit` でも同じ引数が使える。テストは管理画面 CRUD が `tests/Feature/*ControllerTest.php`、API が `tests/Feature/API/`、管理者ログインが `tests/Feature/Auth/` に置かれている。
- `vendor/bin/pint`（または `vendor/bin/pint --dirty --format agent`）— Laravel Pint によるコード整形。PHP ファイルを変更した後は必ず実行すること。
- `php artisan migrate` / `php artisan migrate:refresh --seed` — マイグレーションの実行/リフレッシュ。`DatabaseSeeder` はテストユーザー（`test@example.com`）・管理者・サイト設定・トップスライダー画像（`storage/app/public/image/` に置いたサンプル画像をアップロード時と同じく 1920×1080 に加工して `image/top_image` へ保存。サンプル画像自体は git 管理外で、登録済みのスライダー画像があれば何もしない）・固定ページ・`ContentModelRelation`・呼び出しコンテンツ・Q&A のサンプルを投入する（`ArticleSeeder`/`TagSeeder` は存在するが `DatabaseSeeder` からは呼ばれないため、記事・タグは `--seed` では入らない。必要なら `php artisan db:seed --class=ArticleSeeder` などで個別に実行する）。
- `npm run dev` / `npm run build` — Vite の開発サーバー起動 / 本番ビルド。

スキャフォールディングの規約（`README.md` より）: API コントローラーは `php artisan make:controller API/XxxController --resource`、管理画面側は `php artisan make:controller XxxController --resource`、モデルは `php artisan make:model Xxx -mfs`（マイグレーション・ファクトリー・シーダーを同時生成）。

## アーキテクチャ上のポイント

- 標準的な Laravel 13 構成で、`bootstrap/app.php` に集約されたブートストラップ方式を採用（`Http/Kernel.php` は存在せず、ミドルウェア/例外/ルーティングの設定はすべて `bootstrap/app.php` に書く）。
- ルーティング: Web ルートは `routes/web.php`、API ルートは `routes/api.php`、Artisan コマンドは `routes/console.php`。`routes/api.php` は `App\Http\Controllers\API\*` 配下のコントローラーで `articles`・`call-contents`・`question-answers`（`Route::apiResource(...)->only(['index'])`）と `site-setting`・`resolve`（単発の `GET`）の読み取り専用エンドポイントを提供し、`bootstrap/app.php` の `withRouting()` に登録済み。レスポンス整形は `app/Http/Resources/*Resource.php`（Eloquent API Resource）を使う。エンドポイントには `darkaonline/l5-swagger` 用の `OpenApi\Attributes`（`#[OA\Get(...)]`）を付与しており、ドキュメントは `/api/documentation` で確認できる（`config/l5-swagger.php` の `generate_always` はデフォルト `false` のため、属性を変更したら `php artisan l5-swagger:generate` で再生成する。本番環境では `Http/Middleware/EnsureApiDocsAreEnabled` により 404 になる）。
- `bootstrap/app.php` は `shouldRenderJsonWhen` により、`api/*` 配下へのリクエストまたは JSON を期待するリクエストに対してすでに JSON 形式のエラーレスポンスを返すよう設定済み。
- オートロード設定（`app/composer.json`）: `App\` → `app/app/`（`app` が二重になっている点に注意）、`Database\Factories\` → `app/database/factories/`、`Database\Seeders\` → `app/database/seeders/`。
- 開発環境（`.env`）・テスト環境（`phpunit.xml`）ともにデフォルトの DB ドライバは SQLite。テストはインメモリ SQLite、配列キャッシュ/セッション/メール、同期キューを使用する。
- 認証は `web`（`User` モデル）と `admin`（`Administrator` モデル）の 2 系統が独立して存在する（`config/auth.php` の `guards`/`providers`）。管理者のログイン/ログアウトは `Auth\AdministratorSessionController` が `/admin/login`・`/admin/logout` を担当し、ログイン試行のレート制限は `AppServiceProvider` の `admin-login` リミッターで定義。未ログイン時/ログイン済み時のリダイレクト先（`admin.login`/`admin.index`）は `bootstrap/app.php` の `redirectGuestsTo()`/`redirectUsersTo()` でリクエストパス（`admin*`）ベースに出し分けている。
- 管理画面ルートの書き方（`routes/web.php`。新しいルートを足すときも必ず従うこと）:
  - `Route::prefix('admin')` グループに入っているのはログイン/ログアウトだけ。各 CRUD は `'admin/xxx'` のパスで個別に定義し、`->names('admin.xxx')->middleware('auth:admin')` をそれぞれに付ける。
  - 管理者自身の CRUD は `Route::resource('admin', AdministratorController::class)->parameters(['admin' => 'administrator'])`（`/admin` 一覧〜`/admin/{administrator}`）。`/admin/{administrator}` は他の `/admin/*` を飲み込むため、**必ずファイル末尾に置く**。
  - `admin/tags/search`・`admin/articles/bulk-approval`・`admin/single-pages/reorder` のような独自ルートは、**対応する `Route::resource` より前に**定義する。
- 論理削除（`SoftDeletes`）が全モデル（`User`・`UserDetail`・`Administrator`・`Article`・`Tag`・`SinglePage`・`SinglePageDetail`・`SiteSetting`・`CallContent`・`ContentModelRelation`・`SocialLink`・`TopSliderImage`・`UserSkill`・`QuestionAnswer`・`Question`・`Answer`・`BranchQuestionAnswer`）の方針として採用されている。物理削除は行わない。メールアドレスを持つ `User`・`Administrator` は、`deleted_at IS NULL` の行だけを対象にした生成カラム `unique_email`（`storedAs` + `unique` 制約）でアクティブなレコード間のみの一意性を担保している（素の `email` カラムにはユニーク制約を張らない）。新しくメールアドレス等の一意制約が必要なテーブルを追加する場合はこのパターンを踏襲すること。
- 記事ドメイン: `Article`（`approval` は `ArticleApprovalStatus` enum で下書き/未承認/公開を管理、`user_id` が null の場合は管理者投稿扱い。一覧画面からの個別/一括の公開設定変更は `admin.articles.approval`/`admin.articles.bulk-approval`）が `Tag` と多対多（中間テーブル `article_tag`）。`ArticleController` はサムネイル画像を `image/thumbnail` に保存し（未指定時は `Article::DEFAULT_THUMBNAIL_PATH`）、本文リッチテキストエディタ（Quill）用の画像アップロードエンドポイント（`admin.articles.content-images` → `image/content` に保存）を別途持つ。タグは `TagController@search` で名前のインクリメンタル検索を提供し、記事保存時は `Tag::firstOrCreate` で未登録タグを自動作成しつつ `sync` する。
- `Article` と `SinglePage` は公開期間（`publication_start_datetime`/`publication_end_datetime`、`datetime` キャスト）を持つ。公開期間の日付範囲検索は共通トレイト `Models\Concerns\HasPublicationPeriod` の `filterPublicationPeriod` スコープを使う。記事・固定ページの管理画面一覧は GET パラメータで検索・並び順（`sort`、`項目_asc`/`項目_desc` 形式でデフォルトは `updated_at_desc`。テーブル見出しのクリックで切り替え、見出しは `admin.partials._sortable_th` を使う）を受け付け、不正値はリダイレクトせず `Validator::valid()` で無視する。`SinglePage` は `sort_order` で公開側の表示順を管理しており、一覧画面のドラッグ並び替え（`admin.single-pages.reorder` で保存）は並び順が「表示順」（`sort=sort_order`）かつ検索条件なしのときだけ有効になる（`SinglePageDetail` も `sort_order` 順で取得）。
- 公開側 URL（フロントは別リポジトリ chococo の Nuxt で、API 経由で取得する。ローカルでは `frontend/chococo` に置いているが、`.gitignore` で除外しており git は chococo 側で独立して管理する）: 記事・固定ページは `parent_path` + `slug` から `HasPath` トレイトが `path` を組み立て、`GET /api/resolve?path=...`（`API\ResolveController`）がフロントのルーターとして固定ページ/記事を解決する。`path` の組み立て・一意性検証・resolve のレスポンス仕様は `.claude/rules/public-path.md` を参照（該当ファイルを編集する際に自動的に読み込まれる）。
- 画像とサイト設定: `SinglePage`・`SiteSetting`・`UserDetail`（`User` と 1 対 1、`User::detail()`）・`TopSliderImage` は画像を `public` ディスクに保存し、切り抜き・縮小は共通の `Support\ImageResizer`（GD）を使う。SNS リンク（`SocialLink`）・トップスライダー画像はサイト設定フォームに、スキル（`UserSkill`）はユーザーフォームに埋め込み、`admin.js` の汎用リピーター `initRepeaterRows()` で編集する。保存先・命名規則・Cropper.js による切り抜きの詳細は `.claude/rules/images-and-repeaters.md` を参照。サイト設定は公開側フロントの URL（`front_url`）と API の URL（`api_url`）も持ち、`View\Composers\SiteSettingComposer` により `layouts.admin` ビューへ自動的に注入される（`AppServiceProvider::boot()` で登録）。
- Q&A（`admin.question-answers`、`QuestionAnswer`/`Question`/`Answer`/`BranchQuestionAnswer`）: 簡易版（トップ表示用、`top_view`）と、質問→回答→次の質問をたどる分岐ありの木構造の 2 形態を持ち、管理画面では木全体を入れ子フォームで一括送信して同期する。データ構造・同期のしかた・API の仕様は `.claude/rules/question-answer.md` を参照。
- 呼び出しコンテンツ機能(`CallContent`/`ContentModelRelation`): `CallContent` は専用の管理画面を持たずサイト設定フォームに埋め込んで編集し、呼び出し先のモデル対応表 `ContentModelRelation` は独自の管理画面 CRUD（`admin.content-model-relations`）を持つ。設置場所×データ種別ごとの呼び出し方マトリクス、`CallContentResolver` によるデータ解決、`GET /api/call-contents` のレスポンス仕様など、実装の詳細は `.claude/rules/call-content.md` を参照(該当ファイルを編集する際に自動的に読み込まれる)。
- 登録件数の上限（トップスライダー画像・固定ページの詳細・Q&A の質問 1 件あたりの回答）は `config/limits.php` に集約し、`.env` の `LIMIT_*` で上書きする（管理画面からは変更しない）。フォームリクエストの `array` ルールに `max:` として適用し、画面側は `data-max-rows`/`data-max-answers` を見て上限に達したら `admin.js` が追加ボタンを無効にする。
- 多言語対応: `config('app.available_locales')`（`ja`/`en`）に基づき、`GET /locale/{locale}` → `LocaleController` がセッションへ言語設定を保存し、`Http/Middleware/SetLocale`（`web` ミドルウェアグループに追加済み）がリクエストごとに `App::setLocale()` を反映する。翻訳文字列は `lang/en.json` に集約（キーは日本語の原文）。
- フロントエンドは Vite でエントリーポイントを `app.css`/`app.js`（公開側）と `admin.css`/`admin.js`（管理画面、Bootstrap 5 + Quill を使用）の 2 系統に分けてビルドしている（`vite.config.js`）。管理画面のページネーションは `Paginator::useBootstrapFive()`（`AppServiceProvider`）。
- `app/` には Laravel Boost が生成した独自の AI ガイダンス（`app/CLAUDE.md`、`app/AGENTS.md`、`app/.claude/skills/`）が存在し、PHP/Laravel/Pint/PHPUnit の規約に加え、`database-schema` や `search-docs` などのツールを提供する Boost MCP サーバー（`app/.mcp.json`）を公開している。このガイダンスは `app/` 配下で作業する際に自動的に読み込まれるため、本ファイルでは重複させず、リポジトリ全体の構成と Docker 環境の説明に留める。
