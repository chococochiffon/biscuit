# biscuit
<p>
<img src="https://img.shields.io/badge/-Laravel-E74430.svg?logo=laravel&style=plastic" alt="">
<img src="https://img.shields.io/badge/-Php-777BB4.svg?logo=php&style=plastic" alt="">
<img src="https://img.shields.io/badge/-Mysql-4479A1.svg?logo=mysql&style=plastic" alt="">
<img src="https://img.shields.io/badge/-Docker-1488C6.svg?logo=docker&style=plastic" alt="">
</p>

Biscuit は、記事・固定ページ・ページビルダーを持つ CMS です。管理画面と API を Laravel(このリポジトリ)が、公開側のサイトを Nuxt の [chococo](https://github.com/chococochiffon/chococo) が受け持ちます。変更履歴は [CHANGELOG.md](CHANGELOG.md) にあります。

## インストール(インストーラー)

### 必要な環境

- Linux・macOS・Windows(WSL2)
- Docker(Docker Compose v2 を含む)・git・curl。ホストに PHP・Node.js は要りません
- メモリ 2GB 以上、ディスクの空き 5GB 以上(イメージのビルドに使います)
- 管理画面のログインは確認コードをメールで送るため、メールを送れる SMTP サーバー

### 手順

リリースの版(タグ)を指定して取得し、`install.sh` を動かします。公開側(chococo)は、`install.sh` が同じ番号のタグを取得します。

```
❯ git clone --branch v1.0.0 https://github.com/chococochiffon/biscuit.git
❯ cd biscuit
❯ ./install.sh
```

表示された URL(既定は http://localhost:8080/install)をブラウザで開き、画面の案内に沿って進めます。インストールが終わるまで `install.sh` は動かしたままにしてください(止めても、もう一度 `./install.sh` で再開できます)。インストールの途中は、公開側のサイトは「準備中」と表示されます。

- 既定のポートは、管理画面と API が 8080 番、公開側(chococo)が 80 番です。変えるときは `BISCUIT_ADMIN_PORT=8088 BISCUIT_FRONT_PORT=8090 ./install.sh` のように指定します
- インストーラーは本番向けの構成(`docker/production/compose.yml`。Docker Compose のプロジェクト名は `biscuit-production`)で立ち上げます
- インストールの途中で作ったデータベースを作り直すときは `./install.sh --reset-database` を使います
- `install.sh` の記録は `install.log`、インストーラーの記録は `app/storage/logs/installer.log` に残ります
- インストールの状態と確認の結果は `docker compose --env-file app/.env -f docker/production/compose.yml -p biscuit-production exec app php artisan biscuit:install --status` で見られます

### HTTPS で公開する

HTTPS は、外側のリバースプロキシ(Caddy・nginx など)で割り当てます。インストーラーのサイトの段で `https://` の URL を入れると、Biscuit はリバースプロキシが付けるヘッダー(`X-Forwarded-*`)を信じるように設定します(`app/.env` の `TRUSTED_PROXIES`)。

同じサーバーで Caddy を使う例です。Caddy が 80・443 番を使うため Biscuit のポートを変え、外から直接つながらないよう、このサーバーの中(`127.0.0.1`)だけで待ち受けます。

```
❯ BISCUIT_BIND_ADDRESS=127.0.0.1 BISCUIT_ADMIN_PORT=8080 BISCUIT_FRONT_PORT=3000 ./install.sh
```

```
# /etc/caddy/Caddyfile
example.com {
    reverse_proxy 127.0.0.1:3000
}

admin.example.com {
    reverse_proxy 127.0.0.1:8080
}
```

サイトの段では、公開側の URL に `https://example.com`、管理画面の URL に `https://admin.example.com` を入れます。

### 運用のコマンド(`./biscuit`)

インストールのあとは、Biscuit を置いたディレクトリで `./biscuit` を使います。

```
❯ ./biscuit status    # 版・URL・コンテナ・データベースの状態
❯ ./biscuit doctor    # 動作に問題がないかの診断(問題があれば終了コード 1)
```

`doctor` は、コンテナ・ファイルの書き換え・ディスクの空き(ホスト)と、データベース・マイグレーション・管理者・公開側に届くか・スケジューラー・メール・HTTPS・新しい版・エラーのログ(Biscuit の中)を確かめます。✘ は対応が必要な問題、! は本番で公開する前に確かめてほしい警告です。

### バックアップ

データベース・画像・`.env` を 1 つのファイル(`app/storage/app/private/backups/biscuit-日時-理由.tar.gz`)にまとめます。毎日 3:00 にも自動で作り(スケジューラーが動いているとき)、新しい 7 個を残します。

```
❯ ./biscuit backup                                   # いま作る
❯ ./biscuit backup list                              # いまあるバックアップ
❯ ./biscuit backup copy <名前> <ディレクトリ>          # ホストの別の場所に写す
```

バックアップには `.env`(パスワード・`APP_KEY`)が入るため、Biscuit の中(www-data)だけが読めるように作ります。サーバーが壊れたときに備えて `copy` で別の場所(別のディスク・別のサーバー)にも写し、人に見られない場所に置いてください。`.env` の `BISCUIT_BACKUP_KEEP`(残す数)・`BISCUIT_BACKUP_DAILY=false`(毎日の自動作成を止める)・`BISCUIT_BACKUP_DAILY_AT`(時刻)で変えられます。

### リストア(バックアップから戻す)

```
❯ ./biscuit restore <名前>                           # ./biscuit backup list の「ファイル」
❯ ./biscuit restore /path/to/biscuit-….tar.gz        # ホストのファイル(別のサーバーから写したものなど)
```

データベースと画像を、バックアップの内容にすべて入れ替えます。先にいまの状態を「リストアの前」のバックアップとして取り、戻すあいだはスケジューラーを止めてメンテナンスモードにします。途中で失敗したときは、リストアの前の状態に自動で戻します。古い版のバックアップは、戻したあとにマイグレーションで今の版に合わせます(新しい版のバックアップは、先に Biscuit を更新してから戻してください)。`.env` は戻しません(データベースのパスワード・URL は、いまのサーバーの値を使います。バックアップの `env/.env` は、設定を見直すときに使ってください)。

### 更新

新しい版が出ると、管理画面のダッシュボードとスーパー管理者へのメールで知らせます。更新のコマンド(`biscuit:update`)は次の版で用意する予定です。

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

## ライセンス

[MIT License](LICENSE)
