---
paths:
  - app/app/Models/Concerns/HasPath.php
  - app/app/Models/Concerns/HasPublicationPeriod.php
  - app/app/Models/Article.php
  - app/app/Models/SinglePage.php
  - app/app/Rules/AvailablePath.php
  - app/app/Http/Requests/Concerns/ValidatesPath.php
  - app/app/Http/Requests/*ArticleRequest.php
  - app/app/Http/Requests/*SinglePageRequest.php
  - app/app/Http/Controllers/API/ResolveController.php
  - app/app/Support/Breadcrumbs.php
  - app/app/Http/Resources/ArticleResource.php
  - app/app/Http/Resources/SinglePageResource.php
  - app/resources/views/admin/articles/_form.blade.php
  - app/resources/views/admin/single_pages/_form.blade.php
  - app/tests/Feature/API/ResolveControllerTest.php
---

# 公開側 URL(path)と resolve API

## path の組み立て

- 記事・固定ページはどちらも `parent_path`（親パス、`/` 区切りの階層・任意）と `slug` を持ち、共通トレイト `Models\Concerns\HasPath` が保存時に `path`（例: `/company/about`）を組み立てて保存する。論理削除を除いた一意性は生成カラム `unique_path` で担保する。
- 固定ページの `slug` は必須。記事の `slug` は任意で、未入力なら記事 ID を使う（例: `/news/123`）。新規作成時は ID 確定後に `created` イベントで組み立て直す。
- 数字だけのスラッグは記事 ID 用に予約しているため入力不可。
- `path` はテーブルをまたいで重複させないため、`Rules\AvailablePath` が記事・固定ページの両方を検索して検証する。フォームリクエストの共通ルール・入力整形は `Http\Requests\Concerns\ValidatesPath`。
- カスタムページの URL の先頭(例: `/recipes`。`custom-pages.md` を参照)は、記事・固定ページの親パスの最初の階層(親パスがなければスラッグ)に使えない(`Rules\NotReservedByCustomPage`)。
- 各 Resource にも `path` を含める。

## resolve API

`GET /api/resolve?path=...`（`API\ResolveController`）がフロントのルーターとして動く。

- `/` なら `type=top` とトップの呼び出しコンテンツを返す。
- URL の先頭がカスタムページの種類の先頭なら、カスタムページの一覧(`type=custom_page_list`)か 1 件(`type=custom_page`)を返す(`custom-pages.md` を参照)。
- それ以外はパスから固定ページを、なければ公開済みの記事を解決する。どちらもモデルの `published()` スコープ（記事は公開ステータスが「公開」かつ公開期間内、固定ページは公開期間内。記事一覧 API・呼び出しコンテンツと共通の条件）で絞り込む。
- `type`（`single_page`/`article`）・本文（`data`）・本文内の呼び出しコンテンツ（`call_contents`）を返す。呼び出しコンテンツとの組み合わせ方は `call-content.md` を参照。
- どの `type` にも、パンくず `breadcrumbs`（`Support\Breadcrumbs`。各項目は `label` と `path`、先頭は Home、末尾は表示中のページ、トップは空）を含める。記事・固定ページの途中の階層は、そのパスに公開中の固定ページ（なければ記事）があればタイトルでリンクし、なければ URL の文字列をリンクなし（`path: null`）で出す。カスタムページは「Home › 種類の一覧 › ページ」。公開側で表示するかどうかはレイアウト管理のページの種類ごとの設定（`layout.md`）で切り替える。
- 記事・固定ページの 1 件取得はこの `resolve` に一本化している（`GET /api/articles` はページ送り用の一覧のみ）。
