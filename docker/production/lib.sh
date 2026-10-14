# shellcheck shell=bash
#
# install.sh と ./biscuit で共通に使う、ホスト側の設定と関数。読み込む前に ROOT(リポジトリの直下)と HOST_LOG(ホスト側のログ)を決めておく。

APP="$ROOT/app"
COMPOSE_FILE="$ROOT/docker/production/compose.yml"
# 公開側(chococo)のソース(install.sh が取得する。install.sh・./biscuit が使う)
# shellcheck disable=SC2034
CHOCOCO_DIR="$ROOT/frontend/chococo"
# データベースの起動を待つ時間(秒)
DB_WAIT_SECONDS=180
# app コンテナの中から見たパス
LOCK_FILE="storage/app/private/installed"

# Docker Compose のプロジェクト名(開発用の docker-compose.yml のプロジェクト biscuit と分ける)
PROJECT="${BISCUIT_PROJECT:-biscuit-production}"

# Biscuit の版(app/config/biscuit.php の version)
biscuit_version() {
  sed -n "s/^[[:space:]]*'version' => '\([0-9][0-9.]*\)',.*/\1/p" "$ROOT/app/config/biscuit.php" | head -n 1
}

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

# 管理画面の CSS・JS のビルド(assets サービス)が作るファイルを、ホストのユーザーのものにする
export_host_ids() {
  export HOST_UID HOST_GID
  HOST_UID="$(id -u)"
  HOST_GID="$(id -g)"
}

# PHP の依存関係(本番用。開発用のパッケージは入れない)
install_php_dependencies() {
  compose run --rm --no-deps -T app composer install --no-dev --optimize-autoloader --no-interaction --no-progress
}

# 管理画面の CSS・JS をビルドする(ホストに Node.js がなくてよい)
build_admin_assets() {
  export_host_ids
  compose --profile build run --rm -T assets
}

# 公開側(chococo)のイメージのビルド(nuxt build)が使うメモリ(MB)。測ると 1.1〜1.4GB で、1GB に絞ると落ちたため余裕を見る
BUILD_MEMORY_MB=1300
# メモリとスワップの合計がこれより少ないと、サービスを止めてもビルドできない(app/config/biscuit.php の memory.minimum_mb と同じ値)
MIN_TOTAL_MEMORY_MB=1700

# /proc/meminfo の値(MB)。読めない(macOS など)ときは空
meminfo_mb() {
  awk -v key="$1:" '$1 == key { print int($2 / 1024) }' /proc/meminfo 2>/dev/null || true
}

# いま使えるメモリ(空き + スワップの空き。MB)。わからなければ空
available_memory_mb() {
  local available swap
  available="$(meminfo_mb MemAvailable)"
  swap="$(meminfo_mb SwapFree)"
  [ -z "$available" ] || echo $((available + ${swap:-0}))
}

# メモリとスワップの合計が、イメージのビルドに足りるか(足りなければ、何も変えずに止める)
check_build_memory() {
  local total swap
  total="$(meminfo_mb MemTotal)"
  [ -n "$total" ] || return 0
  swap="$(meminfo_mb SwapTotal)"
  if [ $((total + ${swap:-0})) -lt "$MIN_TOTAL_MEMORY_MB" ]; then
    fail "メモリが足りないため、イメージを作れません(メモリ ${total}MB・スワップ ${swap:-0}MB。合計 ${MIN_TOTAL_MEMORY_MB}MB 以上が要ります)。スワップを足してから、もう一度動かしてください(README の「メモリが少ないサーバー」)。"
  fi
}

# ビルドの前に、使えるメモリが足りなければサービスを止めて空ける(メンテナンス中に使う。起動し直すのは呼んだ側)
free_memory_for_build() {
  local available
  available="$(available_memory_mb)"
  if [ -z "$available" ] || [ "$available" -ge "$BUILD_MEMORY_MB" ]; then
    return 0
  fi
  info "メモリを空けるため、ビルドのあいだは公開側のサイトを止めます"
  compose stop front >>"$HOST_LOG" 2>&1 || true

  available="$(available_memory_mb)"
  [ "$available" -lt "$BUILD_MEMORY_MB" ] || return 0
  info "まだ足りないため、ビルドのあいだは管理画面とデータベースも止めます"
  compose stop web app db >>"$HOST_LOG" 2>&1 || true
  log "build memory available=$(available_memory_mb)MB"
}

# 時間のかかる処理を、出力をログに書きながら動かす。画面には経過時間を出す(止まって見えないように)
run_logged() {
  local label="$1"
  shift
  info "$label"

  local ticker="" started status=0
  started="$(date +%s)"
  if [ -t 1 ]; then
    # このスクリプトが止められたら(Ctrl+C など)、一緒に止まる
    (
      while kill -0 $$ 2>/dev/null; do
        elapsed=$(($(date +%s) - started))
        printf '\r    経過 %d分%02d秒(詳しくは %s)' $((elapsed / 60)) $((elapsed % 60)) "$(basename "$HOST_LOG")"
        sleep 1
      done
    ) &
    ticker=$!
  fi

  "$@" >>"$HOST_LOG" 2>&1 || status=$?

  if [ -n "$ticker" ]; then
    kill "$ticker" 2>/dev/null || true
    wait "$ticker" 2>/dev/null || true
    printf '\r\033[K'
  fi

  return "$status"
}

# データベースのコンテナが healthy になるまで待つ(DB_WAIT_SECONDS 秒で諦める)
wait_for_db() {
  local container waited=0
  container="$(compose ps -q db)"
  while [ "$waited" -lt "$DB_WAIT_SECONDS" ]; do
    [ "$(docker inspect -f '{{.State.Health.Status}}' "$container" 2>/dev/null)" = "healthy" ] && return 0
    sleep 3
    waited=$((waited + 3))
  done
  return 1
}

# .env の値(シングル・ダブルクォートを外す)
env_value() {
  sed -n "s/^$1=//p" "$APP/.env" | tail -n 1 | sed -e "s/^'\(.*\)'$/\1/" -e 's/^"\(.*\)"$/\1/'
}

admin_url() { env_value APP_URL; }
front_url() { env_value FRONT_URL; }

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

# install.sh・./biscuit の処理を同時に 2 つ動かさない(ロックのディレクトリを作れたものだけが動く)
LOCK_DIR="$ROOT/.biscuit.lock"

acquire_lock() {
  if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    local pid
    pid="$(cat "$LOCK_DIR/pid" 2>/dev/null || true)"
    if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
      fail "ほかの Biscuit の処理(install.sh・./biscuit)が動いています(プロセス $pid)。終わってから、もう一度動かしてください。"
    fi
    # 前に動いていた処理が残したロック(もう動いていない)は取り直す
    rm -rf "$LOCK_DIR"
    mkdir "$LOCK_DIR" || fail "ロック($LOCK_DIR)を作れませんでした。"
  fi
  echo "$$" >"$LOCK_DIR/pid"
  trap 'rm -rf "$LOCK_DIR"' EXIT
}
