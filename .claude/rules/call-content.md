---
paths:
  - app/app/Models/CallContent.php
  - app/app/Models/ContentModelRelation.php
  - app/app/Enums/CallType.php
  - app/app/Enums/CallContentType.php
  - app/app/Enums/CallContentPlace.php
  - app/app/Support/CallContentResolver.php
  - app/app/Support/CallContent/**
  - app/app/Rules/ValidCallContentCombination.php
  - app/app/Rules/AllowedTableName.php
  - app/app/Http/Resources/CallContentResource.php
  - app/app/Http/Controllers/API/CallContentController.php
  - app/resources/views/admin/site_settings/**
  - app/resources/js/admin.js
  - app/tests/Feature/**/CallContent*
---

# 呼び出しコンテンツ機能(CallContent)

`CallContent`（`call_type`: ShortSentence/OriginalText/LinkList/Link/Archive/SkillList=表示方法・`call_name`=管理用ラベル・`content_model_relation_id`（`ContentModelRelation` への FK）・`view_count`・`place`: Top/Inside/Others=設置場所）は「どの設置場所にどのデータ種別の呼び出し枠を置くか」を表す設定レコードで、専用の管理コントローラーは持たず `SiteSettingController` の作成/編集フォームに埋め込まれ、`syncCallContents()`（送信された行を id の有無で作成/更新し、送信されなかった既存行は削除）で同期される。同じ「埋め込みリピーター行を private な `syncXxx()` で同期する」パターンは `SinglePageController`（`SinglePageDetail` を `syncDetails()` で同期）にも使われている。

`content_model_relation_id` が指す `ContentModelRelation`（`content_type`: Article/SinglePage/Custom・`model_name`・`table_name` の対応表、管理画面 CRUD あり）が実際の呼び出し先モデルを決め、`table_name` 自体は `Rules\AllowedTableName`（固定許可値 `articles`/`single_pages`/`user_details`、または `user_make_` 接頭辞の動的テーブルのみ許可）でバリデーションされる。

選択可能な組み合わせは表示箇所(`place`)・モデル名(`ContentModelRelation->model_name`。`Article`/`SinglePage`/`UserDetail` の3種のみ)ごとに許可される `call_type` を定義したマトリクス（`CallType::combinationMatrix()`、判定は `CallType::supports(modelName, place)`）で決まり、`CallType` enum の他のメソッド（`allowedForPlace()`/`allowedModelNames()`/`hasFixedViewCount()`）はこのマトリクスから導出される（Custom な `ContentModelRelation`（`UserDetail` 以外の動的テーブル）はどの組み合わせにも該当せず選択不可）。入力・表示の並びと絞り込みの順序は **表示箇所(`place`) → 呼び出し方(`call_type`) → データ種別(モデル名) → 表示件数** で統一している。`Store`/`UpdateSiteSettingRequest` で `Rules\ValidCallContentCombination` により行単位でバリデーションされる（`call_type` は `place` で選択可能かどうか、`content_model_relation_id` は `place`・`call_type` との厳密な組み合わせ（`place` が不正な場合は `call_type` に対する全表示箇所の和集合）、`view_count` は1固定かどうかをチェックする。`place` 自体は組み合わせの起点なので enum チェックのみ）。同じ制約情報は `CallType::jsConstraintsMap()`（`places`: 表示箇所ごとの呼び出し方と呼び出し方ごとのモデル名／`modelNamesByCallType`: 表示箇所未選択時の和集合／`fixedViewCount`）で JSON 化して `admin.js` の `initCallContentRows()` に渡され、`applyPlaceConstraints()`（呼び出し方の絞り込み）→ `applyCallTypeConstraints()`（データ種別の絞り込み・表示件数の固定）の順に適用される。

実データの解決は `Support\CallContentResolver`（`resolve()`/`resolveMany()`）が担い、`contentModelRelation->model_name`（`Article`/`SinglePage`/`UserDetail` のいずれか、該当なしは `InvalidArgumentException`）と `call_type` から `Support\CallContent\{Article,SinglePage,UserDetail}ContentSource` の `get`接頭辞メソッド（例: `getOriginalText`/`getLinkList`/`getArchive`/`getShortSentence`/`getSkillList`。`UserDetail` に `getArchive` 相当の呼び出し方は存在しない）に振り分ける。取得件数・絞り込み条件（`Article` は `approval=Published`、`SinglePage` は `top_page_view`/`sort_order`、`UserDetail` は `view_flag`）は設置場所(`place`)ごとに異なり、3つの設置場所すべてでルールが定義済みで、`CallType::supports()` を満たさない組み合わせ（保存時のバリデーションを経ていない不整合データなど）は `CallContentResolver::resolve()` が `InvalidArgumentException` を投げる。

`GET /api/call-contents`（`place` クエリで絞り込み、未指定時は Top）を整形する `CallContentResource` はこのリゾルバーを呼び出し、各要素を `table_name`（例: `articles`/`single_pages`/`user_details`）をキーにした1フィールドのみのオブジェクト（値は解決済み実データ。`ArticleResource`/`SinglePageResource`/`UserDetailResource` で整形し、単一表示はオブジェクト・一覧表示は配列）として返す（`call_type`/`view_count`/`model_name`/`content_type` 等のメタデータはレスポンスに含めない）。実データ解決に失敗するケース（マトリクス上許可されない組み合わせ、`model_name` 不明など）は例外を伝播させ 500 エラーとする。
