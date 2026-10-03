#!/usr/bin/env bash
#
# Biscuit のインストーラー(ホスト側)。Linux・macOS・Windows(WSL2)の bash で動かす。
#
#   ./install.sh                     インストーラーを起動する(途中からの再開も同じ)
#   ./install.sh --reset-database    インストールの途中で作ったデータベースを消して作り直せるようにする
#   BISCUIT_ADMIN_PORT=8088 BISCUIT_FRONT_PORT=8090 ./install.sh   ポートを変える(既定は管理画面 8080・公開側 80)
#
# 役割: Docker の準備・起動だけを受け持つ。設定の入力はブラウザのインストーラー(/install)で行い、DB・マイグレーションなどは
# Laravel(php artisan biscuit:install)が行う。ブラウザから Docker は操作させず、Laravel が置いた決まった名前の合図
# (app/storage/app/private/installer/host-request.json の action)にだけ反応して、ここに書いた処理を動かす。
# 合図・進み具合のファイルは www-data だけが読み書きできるため、app コンテナの中で読み書きする。
# パスワードなどの秘密の値は、画面にもログにも出さない。

set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
APP="$ROOT/app"
COMPOSE_FILE="$ROOT/docker/production/compose.yml"
# app コンテナの中から見たパス
INSTALLER_DIR="storage/app/private/installer"
REQUEST_FILE="$INSTALLER_DIR/host-request.json"
STATUS_FILE="$INSTALLER_DIR/host-status.json"
LOCK_FILE="storage/app/private/installed"
# ホスト側のログ(app/storage は www-data のものになり、ホストのユーザーは書けないため、リポジトリの直下に置く。*.log は git で除外)
HOST_LOG="$ROOT/install.log"

# Docker Compose のプロジェクト名(開発用の docker-compose.yml のプロジェクト biscuit と分ける)
PROJECT="${BISCUIT_PROJECT:-biscuit-production}"

CHOCOCO_REPOSITORY="${CHOCOCO_REPOSITORY:-https://github.com/chococochiffon/chococo.git}"
CHOCOCO_REF="${CHOCOCO_REF:-master}"
CHOCOCO_DIR="$ROOT/frontend/chococo"

# データベースの起動・公開側の応答を待つ時間(秒)
DB_WAIT_SECONDS=180
FRONT_WAIT_SECONDS=120

info() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m警告:\033[0m %s\n' "$*" >&2; }
fail() { printf '\033[1;31mエラー:\033[0m %s\n' "$*" >&2; exit 1; }

log() {
  mkdir -p "$(dirname "$HOST_LOG")" 2>/dev/null || true
  printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >>"$HOST_LOG" 2>/dev/null || true
}

compose() {
  docker compose --env-file "$APP/.env" -f "$COMPOSE_FILE" -p "$PROJECT" "$@"
}

# app コンテナの中で www-data として動かす(動いていなければ一時的なコンテナで)
in_app() {
  if [ -n "$(compose ps -q --status running app 2>/dev/null)" ]; then
    compose exec -T -u www-data app "$@"
  else
    compose run --rm --no-deps -T -u www-data app "$@"
  fi
}

# .env の値(シングル・ダブルクォートを外す)
env_value() {
  sed -n "s/^$1=//p" "$APP/.env" | tail -n 1 | sed -e "s/^'\(.*\)'$/\1/" -e 's/^"\(.*\)"$/\1/'
}

admin_url() { env_value APP_URL; }
front_url() { env_value FRONT_URL; }

usage() {
  sed -n '3,7p' "$0" | sed 's/^# \{0,1\}//'
}

check_requirements() {
  info "動かすための環境を確かめます"
  command -v docker >/dev/null 2>&1 || fail "Docker が見つかりません。Docker(Windows・macOS は Docker Desktop)を入れてください。"
  docker info >/dev/null 2>&1 || fail "Docker が動いていないか、使う権限がありません。Docker を起動するか、ユーザーを docker グループに入れてください。"
  docker compose version >/dev/null 2>&1 || fail "Docker Compose(docker compose)が使えません。Docker を新しくしてください。"
  command -v git >/dev/null 2>&1 || fail "git が見つかりません。git を入れてください。"
  command -v curl >/dev/null 2>&1 || fail "curl が見つかりません。curl を入れてください。"
}

# ロックファイルは www-data だけが読めるが、ディレクトリは見られるため、ホストから有無だけを確かめる(コンテナを動かさない)
is_installed() {
  [ -f "$APP/$LOCK_FILE" ]
}

# 進み具合を書く(ブラウザのインストーラーが読む)。message は秘密の値を含めない
write_status() {
  local status="$1" stage="$2" message="${3:-}"
  local escaped
  escaped="$(printf '%s' "$message" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g' | awk 'BEGIN { ORS = "\\n" } { print }' | sed 's/\\n$//')"
  printf '{"action":"start-services","status":"%s","stage":"%s","message":"%s","updated_at":"%s"}' \
    "$status" "$stage" "$escaped" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" | in_app sh -c "mkdir -p $INSTALLER_DIR && cat > $STATUS_FILE"
  log "status=$status stage=$stage"
}

prepare() {
  if [ ! -d "$CHOCOCO_DIR/.git" ]; then
    info "公開側(chococo)を取得します($CHOCOCO_REF)"
    mkdir -p "$(dirname "$CHOCOCO_DIR")"
    git clone --depth 1 --branch "$CHOCOCO_REF" "$CHOCOCO_REPOSITORY" "$CHOCOCO_DIR"
  fi

  if [ ! -f "$APP/.env" ]; then
    info ".env を作ります"
    cp "$APP/.env.example" "$APP/.env"
  fi

  export HOST_UID HOST_GID
  HOST_UID="$(id -u)"
  HOST_GID="$(id -g)"

  info "PHP のイメージを作ります(初回は数分かかります)"
  compose build app

  info "PHP の依存関係を入れます"
  compose run --rm --no-deps -T app composer install --no-dev --optimize-autoloader --no-interaction --no-progress

  info "管理画面の CSS・JS をビルドします"
  compose --profile build run --rm -T assets

  # public/ はホストのユーザーのもののため、アップロード画像の公開用のリンク(public/storage)もここで root として作る
  info "ファイルの権限を整えます"
  compose run --rm --no-deps -T -u root app sh -c \
    'mkdir -p storage/app/private storage/app/public storage/logs && chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache && chown www-data .env && chmod 660 .env && ln -sfn ../storage/app/public public/storage'

  info ".env に本番向けの初期値を入れます"
  # ポートは、ホストで BISCUIT_ADMIN_PORT・BISCUIT_FRONT_PORT を指定したときだけその値にする(80 番が使われているときなど)
  local port_options=()
  [ -n "${BISCUIT_ADMIN_PORT:-}" ] && port_options+=(-e "BISCUIT_ADMIN_PORT=$BISCUIT_ADMIN_PORT")
  [ -n "${BISCUIT_FRONT_PORT:-}" ] && port_options+=(-e "BISCUIT_FRONT_PORT=$BISCUIT_FRONT_PORT")
  compose run --rm --no-deps -T -u www-data ${port_options[@]+"${port_options[@]}"} app php artisan biscuit:install --prepare

  info "インストーラーを起動します"
  compose up -d app web
}

wait_for_db() {
  local container waited=0 health
  container="$(compose ps -q db)"
  while [ "$waited" -lt "$DB_WAIT_SECONDS" ]; do
    health="$(docker inspect -f '{{.State.Health.Status}}' "$container" 2>/dev/null || echo starting)"
    [ "$health" = "healthy" ] && return 0
    sleep 3
    waited=$((waited + 3))
  done
  return 1
}

wait_for_front() {
  local waited=0 code
  while [ "$waited" -lt "$FRONT_WAIT_SECONDS" ]; do
    code="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$(env_value BISCUIT_FRONT_PORT)/" || true)"
    [ -n "$code" ] && [ "$code" != "000" ] && return 0
    sleep 3
    waited=$((waited + 3))
  done
  return 1
}

# 合図 start-services: DB・公開側・スケジューラーを起動し、アプリのセットアップ(biscuit:install --step=application)を動かす
start_services() {
  info "サービスを起動して、アプリのセットアップを始めます"

  if [ -z "$(env_value DB_PASSWORD)" ] || [ -z "$(env_value DB_ROOT_PASSWORD)" ]; then
    write_status failed database "データベースの段を終えてから、もう一度お試しください。"
    return
  fi

  write_status running build "公開側のイメージを作っています(初回は数分かかります)"
  if ! compose build front scheduler >>"$HOST_LOG" 2>&1; then
    write_status failed build "公開側のイメージを作れませんでした。install.sh の画面と install.log を確認してください。"
    return
  fi

  write_status running start "サービスを起動しています"
  if ! compose up -d db app web scheduler >>"$HOST_LOG" 2>&1; then
    write_status failed start "サービスを起動できませんでした。install.log を確認してください。"
    return
  fi

  write_status running database "データベースの起動を待っています"
  if ! wait_for_db; then
    write_status failed database "データベースが起動しませんでした。docker compose -p $PROJECT logs db を確認してください。"
    return
  fi

  write_status running application "データベースを用意しています(マイグレーション)"
  local output
  if ! output="$(in_app php artisan biscuit:install --step=application 2>&1)"; then
    local stage
    stage="$(printf '%s\n' "$output" | sed -n 's/^stage=//p' | head -n 1)"
    write_status failed "${stage:-application}" "$(printf '%s\n' "$output" | grep -v '^stage=' | tail -n 5)"
    return
  fi

  write_status running front "公開側のサイトを起動しています"
  compose up -d front >>"$HOST_LOG" 2>&1 || true
  if ! wait_for_front; then
    warn "公開側のサイトの応答を確認できませんでした(インストールは続けられます)。"
  fi

  write_status succeeded "done" "セットアップを終えました"
  info "アプリのセットアップを終えました。ブラウザで次の段へ進んでください。"
}

# ブラウザのインストーラーからの合図を待つ(インストールを終えたら終わる)
watch_requests() {
  info "ブラウザで次の URL を開いて、インストールを進めてください:"
  printf '\n    %s/install\n\n' "$(admin_url)"
  info "インストールが終わるまで、この画面は閉じずに動かしたままにしてください(Ctrl+C で止めても、もう一度 ./install.sh で再開できます)。"

  while true; do
    if is_installed; then
      finish
      return
    fi

    local action
    action="$(in_app sh -c "cat $REQUEST_FILE 2>/dev/null && rm -f $REQUEST_FILE" | sed -n 's/.*"action":"\([a-z-]*\)".*/\1/p' || true)"

    case "$action" in
      "") ;;
      start-services) start_services ;;
      *) log "知らない合図を受け取ったため無視しました: $action" ;;
    esac

    sleep 2
  done
}

finish() {
  compose up -d >>"$HOST_LOG" 2>&1 || true
  info "Biscuit のインストールが完了しました。"
  printf '\n    サイト:     %s\n    管理画面:   %s/admin\n\n' "$(front_url)" "$(admin_url)"
}

reset_database() {
  [ -f "$APP/.env" ] || fail "まだインストーラーを動かしていません。"
  if is_installed; then
    fail "インストール済みのため、データベースは消せません。"
  fi
  warn "インストールの途中で作ったデータベース(Docker のボリューム ${PROJECT}_db-data)を消します。元には戻せません。"
  read -r -p "消してよろしいですか? [y/N] " answer
  [ "$answer" = "y" ] || [ "$answer" = "Y" ] || { info "やめました。"; exit 0; }
  compose rm -s -f db >/dev/null 2>&1 || true
  docker volume rm "${PROJECT}_db-data" >/dev/null 2>&1 || true
  info "データベースを消しました。./install.sh を動かして、もう一度セットアップしてください。"
}

# install.sh を同時に 2 つ動かさない(合図を取り合わないよう、ロックのディレクトリを作れたものだけが動く)
LOCK_DIR="$ROOT/.install.lock"

acquire_lock() {
  if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    local pid
    pid="$(cat "$LOCK_DIR/pid" 2>/dev/null || true)"
    if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
      fail "install.sh はすでに動いています(プロセス $pid)。そちらを使うか、止めてからもう一度動かしてください。"
    fi
    # 前に動いていた install.sh が残したロック(もう動いていない)は取り直す
    rm -rf "$LOCK_DIR"
    mkdir "$LOCK_DIR" || fail "ロック($LOCK_DIR)を作れませんでした。"
  fi
  echo "$$" >"$LOCK_DIR/pid"
  trap 'rm -rf "$LOCK_DIR"' EXIT
}

main() {
  case "${1:-}" in
    -h|--help) usage; exit 0 ;;
    --reset-database) check_requirements; acquire_lock; reset_database; exit 0 ;;
    "") ;;
    *) usage; exit 1 ;;
  esac

  printf '\n  Biscuit Installer\n\n'
  check_requirements
  acquire_lock

  if [ -f "$APP/.env" ] && is_installed; then
    finish
    exit 0
  fi

  prepare
  watch_requests
}

main "$@"
