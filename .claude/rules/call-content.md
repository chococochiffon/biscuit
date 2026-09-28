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
  - app/resources/js/admin/**
  - app/tests/Feature/**/CallContent*
---

# 呼び出しコンテンツ機能(CallContent)

`CallContent`（`call_type`: ShortSentence/OriginalText/LinkList/Link/Archive/SkillList/TileList/Accordion=表示方法・`call_name`=管理用ラベル・`title`/`subtitle`=公開側で表示する見出し・小見出し（任意。空なら見出しなしで表示）・`content_model_relation_id`（`ContentModelRelation` への FK）・`view_count`・`place`: Top/Inside/Others=設置場所）は「どの設置場所にどのデータ種別の呼び出し枠を置くか」を表す設定レコードで、専用の管理コントローラーは持たず `SiteSettingController` の作成/編集フォームに埋め込まれ、`syncCallContents()`（送信された行を id の有無で作成/更新し、送信されなかった既存行は削除）で同期される。埋め込みリピーター行の同期は共通トレイト `Http\Controllers\Concerns\SyncsSortableRows` の `syncSortableRows()` にまとめてあり、`syncCallContents()`・`syncSocialLinks()`・`syncTopSliderImages()`（`SiteSettingController`）、`syncDetails()`（`SinglePageController`）、`syncSkills()`（`UserController`）は行から保存する属性を組み立てて渡すだけになっている（親に属する行は親のリレーションを渡して絞り込む）。

`content_model_relation_id` が指す `ContentModelRelation`（`content_type`: Article/SinglePage/Custom・`model_name`・`table_name` の対応表、管理画面 CRUD あり）が実際の呼び出し先モデルを決め、`table_name` 自体は `Rules\AllowedTableName`（固定許可値 `articles`/`single_pages`/`user_details`/`gallery_images`/`question_answers`、または登録済みのカスタムページの種類の本体のテーブル（例: `user_make_recipes`。詳細・カスタムフォームのテーブルは不可）のみ許可。管理画面の候補も同じ）でバリデーションされる。

選択可能な組み合わせは表示箇所(`place`)・モデル名(`ContentModelRelation->model_name`。`Article`/`SinglePage`/`UserDetail`/`GalleryImage`/`QuestionAnswer` の5種のみ。`GalleryImage` はトップのタイルリスト(TileList)、`QuestionAnswer` はトップの開閉パネル(Accordion)だけ。カスタムページは `table_name` が種類の本体のテーブルのとき `ContentModelRelation::matrixModelName()` が `CustomArticle`/`CustomSinglePage` として扱い、判定・選択肢の絞り込み(`data-model-name`)ともこの名前を使う。`custom-pages.md` を参照)ごとに許可される `call_type` を定義したマトリクス（`CallType::combinationMatrix()`、判定は `CallType::supports(modelName, place)`）で決まり、`CallType` enum の他のメソッド（`allowedForPlace()`/`allowedModelNames()`/`hasFixedViewCount()`）はこのマトリクスから導出される（Custom な `ContentModelRelation` のうち `UserDetail`/`GalleryImage`/`QuestionAnswer` 以外（動的テーブルなど）はどの組み合わせにも該当せず選択不可）。入力・表示の並びと絞り込みの順序は **表示箇所(`place`) → 呼び出し方(`call_type`) → データ種別(モデル名) → 表示件数** で統一している。`Store`/`UpdateSiteSettingRequest` で `Rules\ValidCallContentCombination` により行単位でバリデーションされる（`call_type` は `place` で選択可能かどうか、`content_model_relation_id` は `place`・`call_type` との厳密な組み合わせ（`place` が不正な場合は `call_type` に対する全表示箇所の和集合）、`view_count` は1固定かどうかをチェックする。`place` 自体は組み合わせの起点なので enum チェックのみ）。同じ制約情報は `CallType::jsConstraintsMap()`（`places`: 表示箇所ごとの呼び出し方と呼び出し方ごとのモデル名／`modelNamesByCallType`: 表示箇所未選択時の和集合／`fixedViewCount`）で JSON 化して `admin.js` の `initCallContentRows()` に渡され、`applyPlaceConstraints()`（呼び出し方の絞り込み）→ `applyCallTypeConstraints()`（データ種別の絞り込み・表示件数の固定）の順に適用される。

実データの解決は `Support\CallContentResolver`（`resolve()`）が担い、`contentModelRelation->model_name`（`Article`/`SinglePage`/`UserDetail`/`GalleryImage`/`QuestionAnswer` のいずれか、該当なしは `InvalidArgumentException`）と `call_type` から `Support\CallContent\{Article,SinglePage,UserDetail,GalleryImage,QuestionAnswer}ContentSource` の `get`接頭辞メソッド（例: `getOriginalText`/`getLinkList`/`getArchive`/`getShortSentence`/`getSkillList`/`getTileList`/`getAccordion`。`UserDetail` に `getArchive` 相当の呼び出し方は存在しない）に振り分ける。取得件数・絞り込み条件（`Article` は `approval=Published`、`SinglePage` は短文が `top_page_view`・リンクリストが `link_list_view` と `sort_order`、`UserDetail` は `view_flag`、`GalleryImage` は並び順（`ordered()`）、`QuestionAnswer` は簡易版（`top_view`）を登録順。スキルリストはスキル（`UserSkill`）も読み込み、`UserDetailResource` の `skills` に並び順で入る）は設置場所(`place`)ごとに異なり（ただし `Article`/`SinglePage` はどの呼び出し方でもモデルの `published()` スコープ（記事は公開ステータスが「公開」かつ公開期間内、固定ページは公開期間内）で絞り込んだものだけを対象にする）、3つの設置場所すべてでルールが定義済みで、`CallType::supports()` を満たさない組み合わせ（保存時のバリデーションを経ていない不整合データなど）は `CallContentResolver::resolve()` が `InvalidArgumentException` を投げる。

`GET /api/call-contents`（`place` クエリで絞り込み、未指定時は Top）は `CallContent::forPlace()` スコープで並び順（`sort_order`、同順は id）に取得し、`CallContentResource` がこのリゾルバーを呼び出す。各要素は `call_type`（`CallType::apiName()` による snake_case 文字列。例: `link_list`）・`call_name`・`title`・`subtitle`（未設定は null）と、`table_name`（例: `articles`/`single_pages`/`user_details`）をキーにしたオブジェクト（値は解決済み実データ。`ArticleResource`/`SinglePageResource`/`UserDetailResource`/`GalleryImageResource`/`QuestionAnswerResource` で整形し、単一表示はオブジェクト・一覧表示は配列）として返す（`view_count`/`model_name`/`content_type` 等は含めない）。実データ解決に失敗するケース（マトリクス上許可されない組み合わせ、`model_name` 不明など）は例外を伝播させ 500 エラーとする。

並び順（`sort_order`）はサイト設定画面の行をドラッグで並び替えて設定し（`admin.js` の `initSortableRows()` が隠し input `sort_order` に画面上の順番を入れる）、`syncCallContents()` で保存する（未送信時は行のキーの順）。同じ設置場所の中でこの順に返す。

フロントエンドのページ表示はパス解決 API（`GET /api/resolve?path=...`、`API\ResolveController`）が担い、呼び出しコンテンツと組み合わせる: `/` は `type=top` と Top の呼び出しコンテンツ、それ以外は本文（`data`）と Inside の呼び出しコンテンツを返す。ページの文脈では `CallContentResource::withPageContent()` で本文を渡し、`CallContentResolver::resolve($callContent, $pageContent)` が本文内（Inside）の原文（OriginalText）枠に固定の取得条件（最新記事・先頭の固定ページ）ではなくその本文を入れる。データ種別が本文と異なる原文枠は `CallContentResolver::appliesToPage()` で除外する。その他（Others）はヘッダー・フッターなど共通部品として `GET /api/call-contents?place=3` で取得する想定。
