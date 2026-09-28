---
paths:
  - app/app/Models/CustomPageType.php
  - app/app/Models/CustomPages/**
  - app/app/Models/Concerns/BelongsToCustomPageType.php
  - app/app/Support/CustomPages/**
  - app/app/Enums/CustomPageBaseType.php
  - app/app/Enums/CustomFormType.php
  - app/app/Http/Controllers/CustomPage*Controller.php
  - app/app/Http/Requests/*CustomPage*Request.php
  - app/app/View/Composers/CustomPageTypeComposer.php
  - app/app/Http/Controllers/API/CustomPage*Controller.php
  - app/app/Http/Resources/CustomPage*Resource.php
  - app/app/Support/CallContent/CustomPageContentSource.php
  - app/app/Rules/NotReservedByCustomPage.php
  - app/resources/views/admin/custom_page_types/**
  - app/resources/views/admin/custom_pages/**
  - app/tests/Feature/CustomPage*
---

# カスタムページ(CustomPageType)

管理画面で種類を登録すると、種類ごとのテーブルを実行時に作る機能。サイドメニューの「カスタムページ管理」の下に「種類の管理」(`admin.custom-page-types`)と、登録した種類ごとのページ一覧(`admin.custom-pages.entries`、URL は `/admin/custom-pages/{customPageType}/entries`)を並べる(`View\Composers\CustomPageTypeComposer`)。公開側は下の「公開側」を参照。

## 種類とテーブル

- `CustomPageType`(`custom_page_types`)がカスタム名(`name`)・表示名(`label`)・ベースの型(`base_type`: `CustomPageBaseType` の記事/固定ページ)・並び順を持つ。登録は `config('limits.custom_page_types')`(既定 5)件まで。
- カスタム名は `CustomPageType::normalizeName()` で単数形の snake_case にそろえる(例: `Recipes` → `recipe`)。登録後は変更できず、論理削除してもテーブルを残すため、削除済みの種類とも重複させない(`name` は削除済みを含めて一意)。作るテーブルが既にある名前も使えない。
- テーブル名はモデルのメソッドで組み立てる(カスタム名 `recipe` の例)。
  - 本体 `tableName()`: `user_make_recipes`(`Str::plural`)。記事型はタイトル・本文・サムネイル・公開ステータス、固定ページ型はタイトル・概要・ヘッダー画像・表示順を持ち、どちらもスラッグと公開期間を持つ。画像は記事・固定ページと同じサイズ・保存先(`storeThumbnail()`/`storeHeaderImage()`)。
  - 詳細 `detailsTableName()`: `user_make_recipe_details`(固定ページ型のみ。固定ページの詳細と同じく小見出し・本文・並び順)
  - カスタムフォームの項目定義 `formsTableName()`: `customs_recipe_forms`
  - カスタムフォームの入力値 `formValuesTableName()`: `customs_recipe_form_values`
- テーブルは `Support\CustomPages\CustomPageSchema::create()` が作る。MySQL ではテーブルの作成が暗黙にコミットされトランザクションで囲めないため、`CustomPageTypeController::store()` はテーブルを先に作ってから種類を保存し、保存に失敗したら `drop()` で削除する(作成の途中で失敗した場合は `create()` が作った分を削除する)。外部キーの制約名は MySQL の 64 文字に収まるよう、テーブル名と列名のハッシュから短い名前(`fk_…`)を付ける。
- 種類ごとのテーブルはマイグレーションの管理外。`custom_page_types` のマイグレーションの `down()` が、登録済みの種類(論理削除済みを含む)のテーブルを `CustomPageSchema::drop()` で先に削除するため、`migrate:refresh` でもテーブルは残らない(DB を作り直すと登録済みのカスタムページのデータも消える)。

## 種類ごとのテーブルのモデル

- テーブルが種類ごとに違うため、`Models\CustomPages\`(`CustomPageEntry`・`CustomPageDetail`・`CustomForm`・`CustomFormValue`)は共通トレイト `Models\Concerns\BelongsToCustomPageType` の `forType($type)`/`queryFor($type)` でテーブル(とキャスト)を設定してから使う。ルートモデル結合は使えないので、コントローラーはページの id を受け取って `queryFor($type)->findOrFail()` する。
- `CustomPageEntry`・`CustomPageDetail`・`CustomFormValue` は `$guarded = []`(一括代入の制限なし)にしている。`$guarded` に列を並べると Laravel が列の一覧をクラスごとにキャッシュし、別の種類の列が捨てられるため。フォームリクエストで検証した値だけを渡すこと。
- 外部キーの列名も種類ごとに違うので、`entryForeignKey()`(`user_make_recipe_id`)・`detailForeignKey()`(`user_make_recipe_detail_id`)・`formForeignKey()`(`customs_recipe_form_id`)で参照する。

## カスタムフォーム

- 項目は種類ごとに定義する(種類の編集画面の繰り返し入力。`syncSortableRows()` で同期)。`parts_name`・`customs_form_type`(`CustomFormType`: テキスト/日付/テキストエリア/メールアドレス/プルダウン/ラジオ/チェックボックス)・`customs_form_options`(プルダウン・ラジオ・チェックボックスの選択肢。画面では 1 行に 1 つ書き、JSON の配列で保存)・並び順を持つ。
- 入力値はページ 1 件ごとに、項目・ページ(と固定ページ型は詳細。今は常に null で、詳細の行ごとの入力は未対応)に紐づけて `customs_…_form_values` に JSON で保存する(チェックボックスは選んだ選択肢の配列、それ以外は文字列、未入力は null)。入力欄は `custom_fields[項目の id]` で、`StoreCustomPageEntryRequest` が入力形式ごとのルール(選択肢は定義した中からだけ)を組み立てる。どの項目も任意入力。

## 公開側

- URL は種類ごとの固定の先頭(`publicPath()`、カスタム名の複数形。例: `/recipes`)の下に「/スラッグ」(`CustomPageEntry::path()`)。スラッグは種類の中で一意で、固定ページ型は必須、記事型は未入力なら id を使う(スラッグがあるページは id では開けない)。
- URL の先頭の重なりは両方向で防ぐ。種類の登録時は、chococo の固定のページ(`CustomPageType::RESERVED_PATHS`: articles・gallery・faq)と、記事・固定ページの URL が使っている先頭を使えない(`StoreCustomPageTypeRequest`)。記事・固定ページは、親パスの最初の階層(親パスがなければスラッグ)にカスタムページの先頭(論理削除済みの種類を含む)を使えない(`Rules\NotReservedByCustomPage`、`ValidatesPath::pathRules()`)。
- 公開中の条件と並び順は `CustomPageEntry::publishedQueryFor()`(記事型は公開ステータスが「公開」かつ公開期間内を公開開始日時の新しい順、固定ページ型は公開期間内を表示順)。
- API: `GET /api/custom-page-types`(ナビ用の種類の一覧)、`GET /api/custom-pages/{name}`(種類ごとの一覧。ページネーション)。パス解決 API(`ResolveController`)は、URL の先頭が種類の先頭なら `type=custom_page_list`(一覧。`custom_page_type` だけ返し、一覧は上の API で取る)か `type=custom_page`(1 件。詳細とカスタムフォームの項目 `custom_fields` を含む)を返す。
- `CustomPageEntryResource` は、公開側が記事・固定ページと同じ部品で表示できるよう、記事型は `ArticleResource`、固定ページ型は `SinglePageResource` と同じ項目名で返し、`custom_page_type` で種類を示す。
- 呼び出しコンテンツ: データ種別紐付けの `table_name` を種類の本体のテーブル(例: `user_make_recipes`)にすると、組み合わせのマトリクスでは `ContentModelRelation::matrixModelName()` が種類のベースの型(`CallType::CUSTOM_ARTICLE`/`CUSTOM_SINGLE_PAGE`)として扱う(記事型はトップのリンク・アーカイブ、その他のリンクリスト・リンク・アーカイブ。固定ページ型はトップのリンクリスト・リンク、その他のリンクリスト)。実データは `Support\CallContent\CustomPageContentSource`、API のキーは本体のテーブル名。
