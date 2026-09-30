---
paths:
  - app/app/Models/PageView.php
  - app/app/Services/PageView*.php
  - app/app/Support/PublicPage*.php
  - app/app/Http/Controllers/PageViewController.php
  - app/app/Http/Controllers/DashboardController.php
  - app/app/Http/Controllers/API/PageViewController.php
  - app/app/Http/Controllers/API/MyPageViewController.php
  - app/tests/Feature/API/MyPageViewControllerTest.php
  - app/app/Http/Requests/API/RecordPageViewRequest.php
  - app/config/page_views.php
  - app/database/migrations/*_create_page_views_table.php
  - app/database/factories/PageViewFactory.php
  - app/resources/views/admin/page_views/**
  - app/resources/js/admin/page-views.js
  - app/tests/Feature/PageView*Test.php
  - app/tests/Feature/API/PageViewControllerTest.php
---

# アクセス解析（PV の記録と集計）

公開側のページの表示を 1 PV として `page_views` に記録し、管理画面の「アクセス解析」（`admin.page-views.index`）で集計して表示する。

## PV の定義と記録の流れ

- PV の対象は、パス解決 API で解決できる公開中のページ（トップ・固定ページ・記事・カスタムページとその一覧）。見つからないページ（404）・管理画面・API・マイページ・画像などは記録しない。リロードも 1 PV として数える（`PAGE_VIEW_DUPLICATE_WINDOW_SECONDS` を 1 以上にすると、同じ訪問者が同じパスをその秒数以内にもう一度表示しても数えない）。
- 公開側の表示は chococo が受け持ち、初回の表示では Nuxt サーバーが、ページ移動ではブラウザが biscuit を呼ぶ。そのため `GET /api/resolve` の中では記録しない（IP アドレス・User-Agent・Cookie が閲覧者のものにならず、useFetch のキャッシュで回数もずれるため）。
- chococo のページ（`pages/[...slug].vue`）は、表示し終えたとき（`onMounted`）に `$recordPageView(route.path)`（`plugins/page-view.client.ts`）で chococo のサーバーの `POST /api/page-views`（`server/api/page-views.post.ts`）へ送る。chococo のサーバーは、閲覧者の IP アドレス（`X-Forwarded-For` があればその値）・User-Agent・Referer（最初の表示は `document.referrer`、ページ移動は直前の chococo のページの URL）・訪問者の識別子・セッションの識別子と、ログイン中ならトークン（`Authorization: Bearer`）を付けて、biscuit の `POST /api/page-views`（`API\PageViewController`）へ中継する。
- biscuit は共有の鍵（`X-Page-View-Key` と `config('page_views.forward_key')`、`.env` の `PAGE_VIEW_FORWARD_KEY`。chococo の `NUXT_PAGE_VIEW_KEY` と同じ値）が一致する中継だけを受け付ける（`RecordPageViewRequest::authorize()`。未設定なら 403 で、chococo も鍵が空なら送らない）。回数は閲覧者の IP アドレスごとに `throttle:page-views`（`AppServiceProvider`）で制限する。
- パスは `Support\PublicPageResolver`（パス解決 API の `API\ResolveController` と共通）で解決し、見つからなければ 404 で記録しない。解決したページ（`Support\PublicPage`）の `contentType()`・`contentId()` を記録する。
- 監査ログは残さない（閲覧の記録で管理の操作ではないため、`AuditLogCoverageTest` の除外に入れている）。

## 記録する値（`Services\PageViewService`）

- `content_type`: `top`・`article`・`single_page`・`custom_page:種類の名前`・`custom_page_list:種類の名前`（監査ログの対象の種類と同じ書き方）。`content_id` はトップ・カスタムページの一覧では null。`path` は正規化したパス（先頭に `/`、末尾の `/` なし）。
- `visitor_id`: chococo の HttpOnly の Cookie `biscuit_visitor_id`（400 日）に入れたランダムな UUID。送られなかった、または UUID でなければ biscuit が新しく発行し、応答の `visitor_id` を chococo が Cookie に保存する。ユニークユーザー（UU）の集計に使う。
- `session_id`: chococo の Cookie `chococo_pv_session`（最後の表示から 30 分）の値の HMAC-SHA256。`ip_hash`: IP アドレスを `inet_pton`/`inet_ntop` で書き方をそろえてから HMAC-SHA256（鍵は `APP_KEY`）。IP アドレス・セッションの識別子そのものは保存しない。
- `user_id`: `$request->user('sanctum')`（トークンが無効なら null）。`user_agent`（512 文字まで）・`referer`（2048 文字まで）はそのまま保存する。
- Bot の除外は `config('page_views.excluded_user_agents')` の正規表現（初期値は空ですべて記録）。
- 保存は `PageViewService::store()` にまとめてある。キュー・非同期の INSERT に変えるときはここだけを変え、コントローラーから `PageView` を直接触らない。
- `page_views` は追記するだけで変更しないため、論理削除の方針の例外として `deleted_at` を持たない。書き込みを軽くするため外部キーは張らず、インデックスは `viewed_at`・`(content_type, content_id, viewed_at)`・`(visitor_id, viewed_at)`・`user_id` だけにしている。

## 集計（`Services\PageViewStatsService`）

- `summary()`（今日・昨日・今月・累計の PV と UU）、`views()`・`uniqueVisitors()`（期間。省略すると累計）、`daily()`（日別の PV・UU。記録のない日も 0 で含める）、`ranking()`（`content_type`・`content_id` ごとの PV の多い順。同じなら UU の多い順。件数は `config('page_views.ranking_limit')`）、`contentViews()`（1 件のコンテンツの PV）。期間は両端を含み、日付は日本時間（`viewed_at` は日本時間の `datetime`）。
- ランキングの表示名は、記事・固定ページ・カスタムページは削除済みでもタイトル、トップは「トップ」、カスタムページの一覧は「種類の表示名 一覧」。
- 今は `page_views` から直接数える。記録が増えて重くなったら、日別の集計テーブル（例: `page_view_daily_stats`。`date`・`content_type`・`content_id` で一意）をスケジューラーで作り、このクラスの中だけを集計テーブルを読むように変える。

## 管理画面（`PageViewController`）

- 今日・昨日・今月・累計の PV/UU のカードはパーシャル `admin.page_views._summary`（`PageViewStatsService::summary()` を `$summary` で渡す）で、ダッシュボード（`DashboardController`）にも表示する。
- ログインしている管理者なら誰でも見られる（IP アドレスなどは表示しない）。閲覧だけで、記録は変更しない。
- 人気コンテンツの期間は GET パラメータ `period`（`today`・`7days`・`30days`（既定）・`month`・`custom`）。`custom` は `from`・`to`（日付。片方だけでもよく、逆なら入れ替える）。不正な値はリダイレクトせずに無視して既定にする。日別の推移はいつも直近 30 日（`PageViewController::DAILY_DAYS`）。推移は PV・UU の折れ線グラフ（`resources/js/admin/page-views.js` の `initPageViewCharts()` が `data-daily` から SVG を描く。縦線とツールチップはポインター・キーボードの ←→ で動かす。系列の色は CSS 変数 `--chart-series-1`・`--chart-series-2`）で、同じ値を折りたたみの表（「表で見る」）でも見られる。

## マイページのダッシュボード（`API\MyPageViewController`）

- `GET /api/me/page-views`（`auth:sanctum`）は、ログイン中のユーザーの記事（論理削除したものを除く）の PV だけを集計して返す（`PageViewStatsService::forUserArticles()` で絞り込んだインスタンスを使う）。サイト全体の数字はユーザーに見せない。
- 返す値は `summary`（今日・昨日・今月・累計の PV/UU）、`daily`（直近 30 日）、`ranking`（直近 30 日の PV の多い順に 10 件。`article_id`・`title`・`path`・PV・UU）。
- chococo は `pages/mypage/index.vue` で BFF の `/api/me/**` の中継を通して取得し、推移は `components/mypage/PageViewChart.vue`（管理画面の `page-views.js` と同じ見た目の SVG の折れ線グラフ。「表で見る」付き）で表示する。
