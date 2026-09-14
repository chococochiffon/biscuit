# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## リポジトリ構成

このリポジトリは Laravel アプリケーションを内包するトップレベルのプロジェクトコンテナです。Laravel アプリ本体は `app/` 配下にすべて存在します（`app/app`、`app/routes`、`app/database` など。Laravel 自身が持つ内側の `app/` ディレクトリと紛らわしいので注意）。リポジトリルートの `docker/` と `docker-compose.yml` はローカル開発環境の定義です。`composer`/`artisan`/`npm` コマンドはすべて `app/` の中で実行してください。

このプロジェクトは一度、素の Laravel 13 スケルトンにリセットされています（コミット履歴参照）。以前のイテレーションにあったドメインモデル/ファクトリはほぼ削除されており、現状はデフォルトの `User` モデル/マイグレーションのみが存在します。ドメインモデル、マイグレーション、コントローラー、ルートは基本的にこれから作り直す前提で考えてください。

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
- `app/` には Laravel Boost が生成した独自の AI ガイダンス（`app/CLAUDE.md`、`app/AGENTS.md`、`app/.claude/skills/`）が存在し、PHP/Laravel/Pint/PHPUnit の規約に加え、`database-schema` や `search-docs` などのツールを提供する Boost MCP サーバー（`app/.mcp.json`）を公開している。このガイダンスは `app/` 配下で作業する際に自動的に読み込まれるため、本ファイルでは重複させず、リポジトリ全体の構成と Docker 環境の説明に留める。
