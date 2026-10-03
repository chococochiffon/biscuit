# biscuit
<p>
<img src="https://img.shields.io/badge/-Laravel-E74430.svg?logo=laravel&style=plastic" alt="">
<img src="https://img.shields.io/badge/-Php-777BB4.svg?logo=php&style=plastic" alt="">
<img src="https://img.shields.io/badge/-Mysql-4479A1.svg?logo=mysql&style=plastic" alt="">
<img src="https://img.shields.io/badge/-Docker-1488C6.svg?logo=docker&style=plastic" alt="">
</p>

## インストール(インストーラー)

サーバーや手元の PC に Biscuit を立ち上げるときは、インストーラーを使います。Docker(Docker Compose を含む)・git・curl が使える Linux・macOS・Windows(WSL2)で動きます。ホストに PHP・Node.js は要りません。

```
❯ git clone https://github.com/chococochiffon/biscuit.git
❯ cd biscuit
❯ ./install.sh
```

表示された URL(既定は http://localhost:8080/install)をブラウザで開き、画面の案内に沿って進めます。インストールが終わるまで `install.sh` は動かしたままにしてください(止めても、もう一度 `./install.sh` で再開できます)。

- 既定のポートは、管理画面と API が 8080 番、公開側(chococo)が 80 番です。変えるときは `BISCUIT_ADMIN_PORT=8088 BISCUIT_FRONT_PORT=8090 ./install.sh` のように指定します
- インストーラーは本番向けの構成(`docker/production/compose.yml`。Docker Compose のプロジェクト名は `biscuit-production`)で立ち上げます。HTTPS は、外側のリバースプロキシなどで用意してください
- インストールの途中で作ったデータベースを作り直すときは `./install.sh --reset-database` を使います
- `install.sh` の記録は `install.log`、インストーラーの記録は `app/storage/logs/installer.log` に残ります

以下は、開発用の環境(`docker-compose.yml`)の作り方です。

## 開発環境のセットアップ

リポジトリを取得し、`app/.env` を作成します。`.env.example` は Docker Compose の MySQL（`db` サービス）を使う設定になっているので、コピーすればそのまま使えます。

```
❯ git clone git@github.com:chococochiffon/biscuit.git
❯ cd biscuit
❯ cp app/.env.example app/.env
```

コンテナを起動します。`app` コンテナは起動時に `composer install` を実行します。`biscuit` データベースは MySQL の初回起動時に作成されます。

```
❯ docker compose up -d
```

アプリケーションキーを生成します。

```
❯ docker compose exec app php artisan key:generate
```

管理画面の CSS・JS をビルドします。`app` コンテナには Node.js が入っていないので、ホスト側の `app/` で実行してください。

```
❯ cd app
❯ npm install
❯ npm run build
```

続けて「storage の権限」と「マイグレーション」の手順を済ませると、http://localhost/admin から管理画面にログインできます。phpMyAdmin は http://localhost:8081 で開けます。

## storage の権限

`storage` と `bootstrap/cache` は php-fpm（`www-data`）が書き込むため、所有者と権限をコンテナ内で設定します。

```
❯ docker compose exec -u root app chown -R www-data:www-data storage bootstrap/cache
```
```
❯ docker compose exec -u root app chmod -R 775 storage bootstrap/cache
```

アップロード画像を表示するため、`public/storage` のシンボリックリンクもコンテナ内で作成します（リンク先がコンテナ内の絶対パスになるため、ホスト側からはリンク切れに見えますが正常です）。

```
❯ docker compose exec app php artisan storage:link
```

`docker compose exec app php artisan test` などをコンテナ内で root のまま実行すると、`storage/framework` や `bootstrap/cache` に root 所有のファイルができ、画面表示時に書き込みエラー（`touch(): Utime failed`）になります。その場合は所有者を戻してください。

```
❯ docker compose exec -u root app chown -R www-data:www-data storage/framework bootstrap/cache
```

## マイグレーション

コマンドはコンテナ内で実行します。シーダーが画像を保存するため、`www-data` として実行してください（root で実行すると、保存した画像が root 所有になります）。

### 実行
```
❯ docker compose exec -u www-data app php artisan migrate
```

### リフレッシュ（初期データの投入）
データベースを作り直し、初期データ（管理者・サイト設定・トップスライダー画像・固定ページ・記事とタグ・Q&A など）を投入します。**既存のデータはすべて消えます。**

```
❯ docker compose exec -u www-data app php artisan migrate:refresh --seed
```

初期データの管理者は `admin@example.com` / `password` でログインできます。
