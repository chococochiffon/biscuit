<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Biscuit のバージョン
    |--------------------------------------------------------------------------
    |
    | 管理画面のダッシュボードのシステム情報に表示する。リリースのたびに更新する。
    |
    */

    'version' => '0.1.0',

    /*
    |--------------------------------------------------------------------------
    | 更新できるバージョンの確認
    |--------------------------------------------------------------------------
    |
    | GitHub のリリース(repository の最新のリリース。タグは v1.2.3 の形)を 1 日 1 回確かめ、上の version より新しければ
    | スーパー管理者に知らせる(ダッシュボード・管理画面の上部・メール)。結果は cache_hours 時間、問い合わせに失敗したときは
    | failure_cache_minutes 分だけキャッシュする。BISCUIT_UPDATE_CHECK=false で確かめない(外へ問い合わせない)。
    | メールは biscuit:check-update(毎日のスケジュール)が送るため、本番では php artisan schedule:run を cron で動かす。
    |
    */

    'update_check' => [
        'enabled' => (bool) env('BISCUIT_UPDATE_CHECK', true),
        'repository' => env('BISCUIT_UPDATE_REPOSITORY', 'chococochiffon/biscuit'),
        'cache_hours' => 24,
        'failure_cache_minutes' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Biscuit からのお知らせ
    |--------------------------------------------------------------------------
    |
    | Biscuit の開発元が配信するお知らせ(リポジトリの main の announcements.json)を 1 日 1 回読み、管理画面のダッシュボードに出す。
    | 重要・セキュリティのお知らせは管理画面の全画面の上部にも出し、biscuit:check-announcements(毎日のスケジュール)が
    | スーパー管理者に 1 回だけメールで知らせる。BISCUIT_ANNOUNCEMENTS=false で読まない(外へ問い合わせない)。
    |
    */

    'announcements' => [
        'enabled' => (bool) env('BISCUIT_ANNOUNCEMENTS', true),
        'url' => env('BISCUIT_ANNOUNCEMENTS_URL', 'https://raw.githubusercontent.com/chococochiffon/biscuit/main/announcements.json'),
        'cache_hours' => 24,
        'failure_cache_minutes' => 60,
        // ダッシュボードに出す件数(新しい順)
        'dashboard_limit' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | ダッシュボードのシステムの警告
    |--------------------------------------------------------------------------
    |
    | ディスクの空きがこの割合(%)を下回ったら警告する。
    | エラーのログは直近 error_log_hours 時間の分を数える。
    |
    */

    'disk_free_warning_percent' => (int) env('BISCUIT_DISK_FREE_WARNING_PERCENT', 10),

    'error_log_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | 公開側(chococo)の固定のページ
    |--------------------------------------------------------------------------
    |
    | パス解決 API を通らない chococo のページのパス。リンク切れのチェックで、これらへのリンクは切れていないものとして扱う。
    | 末尾が /* のものは、その下の階層もすべて含む。chococo にページを足したらここにも足す。
    |
    */

    'front_static_paths' => [
        '/articles',
        '/faq',
        '/gallery',
        '/login',
        '/forgot-password',
        '/reset-password',
        '/invitation',
        '/mypage',
        '/mypage/*',
    ],

];
