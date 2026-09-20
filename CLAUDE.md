# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## リポジトリ構成

このリポジトリは Laravel アプリケーションを内包するトップレベルのプロジェクトコンテナです。Laravel アプリ本体は `app/` 配下にすべて存在します（`app/app`、`app/routes`、`app/database` など。Laravel 自身が持つ内側の `app/` ディレクトリと紛らわしいので注意）。リポジトリルートの `docker/` と `docker-compose.yml` はローカル開発環境の定義です。`composer`/`artisan`/`npm` コマンドはすべて `app/` の中で実行してください。

「biscuit」は記事投稿型サイト（ブログ）の管理画面と公開側を持つアプリケーションとして作り込まれている最中です。デフォルトの `User` モデル、管理者機能（`Administrator` モデル、`admin` 認証ガード、`/admin` 配下の認証・CRUD 一式）に加え、記事（`Article`）・タグ（`Tag`）・固定ページ（`SinglePage`）・サイト設定（`SiteSetting`）のドメインモデルとその管理画面 CRUD が実装されている。

## 環境構築

ローカル開発は Docker Compose 経由で動かします（リポジトリルートで実行）。

```
docker compose up -d
```

これにより以下が起動します。

- `app` — PHP-FPM 8.5 コンテナ（`docker/php/Dockerfile` からビルド）。`./app` を `/var/www/app` にマウントし、コンテナ起動時（ENTRYPOINT）に `composer install` を実行してから `php-fpm` を起動する。
- `nginx` — `app/public` を配信し、`.php` へのリクエストを `app:9000`（php-fpm）へプロキシする（`docker/nginx/default.conf`）。ポート 80 で公開。
- `db` — MySQL 9（root パスワードは `password`、データベース名は `database`）。ポート 3306 で公開。文字コードは `utf8mb4` / `utf8mb4_unicode_ci` 固定（`docker/db/my.cnf`）。
- `phpmyadmin` — ポート 8081 で公開。

ローカルの `.env`（デフォルト）は Dockerized MySQL ではなく SQLite（`DB_CONNECTION=sqlite`）を使う設定になっています。MySQL コンテナを使いたい場合は `DB_*` 系の環境変数を `db` サービス向けに書き換えてください。

初回セットアップ（`app/` の中で実行）:
```
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate
```

`composer run setup` は上記の大部分（install、`.env` コピー、キー生成、migrate、npm install/build）を一括で行います。

`docker compose exec app <command>` で `artisan`/`pint` などをコンテナ内から実行できますが、コンテナ内プロセスは root で動くため、`make:*` などファイルを生成するコマンドを叩くとホスト側で root 所有のファイルが作られます。編集できない場合は `docker compose exec -u root app chown -R $(id -u):$(id -g) <path>` で所有権をホストユーザーに戻してください。また `docker compose exec` 経由の git はリポジトリルートの `.git` を見つけられない（`app/` だけがマウントされているため）ので、`--dirty` を使う Pint はホスト側の git か、対象ファイルを明示指定して実行する必要があります。

## よく使うコマンド

すべて `app/` の中で実行します。

- `php artisan dev`（または `composer dev`）— アプリ本体・キューリスナー・Vite dev サーバーを同時起動。
- `php artisan test`（または `composer test`）— テストスイート全体を実行（実行前に config をクリアする）。特定のテストのみ実行する場合は `php artisan test --filter=testName`、ファイル指定は `php artisan test tests/Feature/ExampleTest.php`。`vendor/bin/phpunit` でも同じ引数が使える。
- `vendor/bin/pint`（または `vendor/bin/pint --dirty --format agent`）— Laravel Pint によるコード整形。PHP ファイルを変更した後は必ず実行すること。
- `php artisan migrate` / `php artisan migrate:refresh --seed` — マイグレーションの実行/リフレッシュ。
- `npm run dev` / `npm run build` — Vite の開発サーバー起動 / 本番ビルド。

このプロジェクトで使われているスキャフォールディングの規約（`README.md` より）:
- API コントローラー: `php artisan make:controller API/XxxController --resource`
- Web コントローラー: `php artisan make:controller XxxController --resource`
- モデル+マイグレーション+ファクトリ+シーダーをまとめて作成: `php artisan make:model Xxx -mfs`
- 既存テーブルへの単体マイグレーション: `php artisan make:migration create_xxx_table --table=xxx`

## アーキテクチャ上のポイント

- 標準的な Laravel 13 構成で、`bootstrap/app.php` に集約されたブートストラップ方式を採用（`Http/Kernel.php` は存在せず、ミドルウェア/例外/ルーティングの設定はすべて `bootstrap/app.php` に書く）。
- ルーティング: Web ルートは `routes/web.php`、Artisan コマンドは `routes/console.php`。`routes/api.php` はまだ存在しない。API を追加する際は新規作成し `bootstrap/app.php` の `withRouting()` に登録すること（README の `API/` コントローラー規約からも API ルートの導入が見込まれている）。
- `bootstrap/app.php` は `shouldRenderJsonWhen` により、`api/*` 配下へのリクエストまたは JSON を期待するリクエストに対してすでに JSON 形式のエラーレスポンスを返すよう設定済み。
- オートロード設定（`app/composer.json`）: `App\` → `app/app/`（`app` が二重になっている点に注意）、`Database\Factories\` → `app/database/factories/`、`Database\Seeders\` → `app/database/seeders/`。
- 開発環境（`.env`）・テスト環境（`phpunit.xml`）ともにデフォルトの DB ドライバは SQLite。テストはインメモリ SQLite、配列キャッシュ/セッション/メール、同期キューを使用する。
- 認証は `web`（`User` モデル）と `admin`（`Administrator` モデル）の 2 系統が独立して存在する（`config/auth.php` の `guards`/`providers`）。管理者向けルートはすべて `routes/web.php` の `/admin` プレフィックス配下にまとまっており、`Route::resource('admin', AdministratorController::class)->parameters(['admin' => 'administrator'])` で `/admin`（一覧）〜`/admin/{administrator}`（詳細・編集・更新・削除）を提供し、ログイン/ログアウトは `Auth\AdministratorSessionController` が `/admin/login`・`/admin/logout` を担当する。未ログイン時/ログイン済み時のリダイレクト先（`admin.login`/`admin.index`）は `bootstrap/app.php` の `redirectGuestsTo()`/`redirectUsersTo()` でリクエストパス（`admin*`）ベースに出し分けている。ログイン試行のレート制限は `AppServiceProvider` の `admin-login` リミッターで定義。
- 論理削除（`SoftDeletes`）が全モデル（`User`・`Administrator`・`Article`・`Tag`・`SinglePage`・`SiteSetting`）の方針として採用されている。物理削除は行わない。メールアドレスを持つ `User`・`Administrator` は、`deleted_at IS NULL` の行だけを対象にした生成カラム `unique_email`（`storedAs` + `unique` 制約）でアクティブなレコード間のみの一意性を担保している（素の `email` カラムにはユニーク制約を張らない）。新しくメールアドレス等の一意制約が必要なテーブルを追加する場合はこのパターンを踏襲すること。
- 記事ドメイン: `Article`（`approval` は `ArticleApprovalStatus` enum で下書き/未承認/公開を管理、`user_id` が null の場合は管理者投稿扱い）が `Tag` と多対多（中間テーブル `article_tag`）。`ArticleController` はサムネイル画像を `image/thumbnail` に保存し（未指定時は `Article::DEFAULT_THUMBNAIL_PATH`）、本文リッチテキストエディタ（Quill）用の画像アップロードエンドポイント（`admin.articles.content-images` → `image/content` に保存）を別途持つ。タグは `TagController@search` で名前のインクリメンタル検索を提供し、記事保存時は `Tag::firstOrCreate` で未登録タグを自動作成しつつ `sync` する。
- `SinglePage`・`SiteSetting` も画像（ヘッダー画像／サイトアイコン・サイト画像）を `public` ディスクへ `年月日時分秒_テーブル名_id` 命名で保存する共通パターンを持つ。`SiteSetting` は `View\Composers\SiteSettingComposer` により `layouts.admin` ビューへ自動的に注入される（`AppServiceProvider::boot()` で登録）。
- 多言語対応: `config('app.available_locales')`（`ja`/`en`）に基づき、`GET /locale/{locale}` → `LocaleController` がセッションへ言語設定を保存し、`Http/Middleware/SetLocale`（`web` ミドルウェアグループに追加済み）がリクエストごとに `App::setLocale()` を反映する。翻訳文字列は `lang/en.json` に集約（キーは日本語の原文）。
- フロントエンドは Vite でエントリーポイントを `app.css`/`app.js`（公開側）と `admin.css`/`admin.js`（管理画面、Bootstrap 5 + Quill を使用）の 2 系統に分けてビルドしている（`vite.config.js`）。管理画面のページネーションは `Paginator::useBootstrapFive()`（`AppServiceProvider`）。
- `app/` には Laravel Boost が生成した独自の AI ガイダンス（`app/CLAUDE.md`、`app/AGENTS.md`、`app/.claude/skills/`）が存在し、PHP/Laravel/Pint/PHPUnit の規約に加え、`database-schema` や `search-docs` などのツールを提供する Boost MCP サーバー（`app/.mcp.json`）を公開している。このガイダンスは `app/` 配下で作業する際に自動的に読み込まれるため、本ファイルでは重複させず、リポジトリ全体の構成と Docker 環境の説明に留める。
