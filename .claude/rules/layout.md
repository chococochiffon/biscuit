---
paths:
  - app/app/Models/Layout.php
  - app/app/Models/LayoutBlock.php
  - app/app/Models/LayoutNavItem.php
  - app/app/Enums/NavItemLinkType.php
  - app/app/Enums/LayoutRegion.php
  - app/app/Enums/LayoutBlockType.php
  - app/app/Enums/LayoutPageType.php
  - app/app/Enums/SidebarPosition.php
  - app/app/Http/Controllers/LayoutController.php
  - app/app/Http/Controllers/API/LayoutController.php
  - app/app/Http/Requests/UpdateLayoutRequest.php
  - app/app/Http/Resources/LayoutBlockResource.php
  - app/app/Support/HtmlSanitizer.php
  - app/resources/views/admin/layouts/**
  - app/resources/js/admin/layouts.js
  - app/database/seeders/LayoutSeeder.php
  - app/tests/Feature/**/LayoutControllerTest.php
---

# レイアウト管理（Layout / LayoutBlock）

公開側（chococo）のヘッダー・サイドバー・フッターに何を置くかを、管理画面の「レイアウト管理」（`admin.layouts.edit`/`admin.layouts.update`。一覧・登録画面は持たず 1 画面で上書き保存する）で編集する。

## データ構造

- `Layout`: ページの種類（`page_type`: `LayoutPageType` = Top/Article/SinglePage/Other）ごとのサイドバーの位置（`sidebar_position`: `SidebarPosition` = None/Left/Right）と、パンくずを表示するか（`show_breadcrumbs`）。ページの種類ごとに 1 件で、保存時は `updateOrCreate`。取得は `Layout::forPageTypes()`（未登録の種類は、サイドバーなし・パンくずはトップ以外で表示の保存していないレイアウト）。カスタムページは記事型を Article・固定ページ型を SinglePage、カスタムページの一覧や記事一覧・ギャラリー・FAQ は Other として扱う。
- `LayoutBlock`: 領域（`region`: `LayoutRegion` = Header/Sidebar/Footer）に置く部品。`block_type`（`LayoutBlockType`）は SiteTitle/NavMenu/SocialLinks/FreeText/Copyright/CallContent。部品は全ページ共通で、ページの種類で変わるのはサイドバーを出すかどうかと位置だけ。並び順は領域の中の `sort_order`（`HasSortOrder`）。
  - 見出し（`title`/`subtitle`）は `LayoutBlockType::hasHeading()` が真の部品（FreeText・CallContent）だけが持つ。
  - CallContent の部品は `call_type`/`content_model_relation_id`/`view_count` を持ち、設置場所「レイアウトの部品」（`CallContentPlace::Layout` = `LayoutBlock::CALL_CONTENT_PLACE`。サイト設定の呼び出しコンテンツでは選べない）の組み合わせ（`CallType` のマトリクス）で選ぶ。検証は `ValidCallContentCombination` に表示箇所を固定で渡し（第 2 引数）、実データは `LayoutBlock::toCallContent()` で保存しない `CallContent` を作って `CallContentResolver`/`CallContentResource` で解決する。
  - NavMenu の部品は項目 `LayoutNavItem`（`navItems()`、並び順は `sort_order`）を持つ。項目のリンク先は `link_type`（`NavItemLinkType` = Url/SinglePage/CustomPageType）に応じて `url`（`/`・`#`・http(s)・mailto だけ許可）・`single_page_id`・`custom_page_type_id` のどれかで、表示名 `label` は URL では必須、固定ページ・カスタムページの一覧では空ならページのタイトル・種類の表示名を使う。項目の件数の上限は `config('limits.layout_nav_items')`。公開側に出す項目は `LayoutBlock::navMenuItems()` が組み立て、項目が未登録なら Home・固定ページ（リンクリスト表示対象）・Articles・カスタムページの種類の一覧・Gallery・FAQ を自動で並べる（公開期間外の固定ページ・削除した種類への項目は出さない）。
  - FreeText の部品は `content`（Quill で入力した HTML）を持ち、保存時に `Support\HtmlSanitizer::clean()` で許可したタグ・属性（Quill の書式・リンク・リスト。リンク先は http(s)・mailto・`/`・`#` だけ）以外を取り除く。
  - 部品の種類で使わない項目は、フォームリクエストの `exclude_unless` で捨て、コントローラーでも null にして保存する。ナビメニューでなくなった部品・削除した部品の項目は論理削除する。
  - もとの呼び出しコンテンツ「その他」は、マイグレーション `move_other_call_contents_to_layout_blocks` でレイアウトの部品に移した（固定ページのリンク一覧はナビメニューが代わりに並べるため移さず、それ以外はフッターの末尾へ）。
- データ種別紐付け（`ContentModelRelation`）は、レイアウトの部品で使用中なら削除できない。

## 管理画面

`resources/js/admin/layouts.js` の `initLayoutBlocks()` が、領域ごとの行の追加・削除、ハンドルのドラッグでの並び替え・領域の間の移動（行の隠し input `region`/`sort_order` を画面上の位置に合わせる）、部品の種類による入力欄の切り替え（使わない入力欄は `disabled` にして送信しない）、自由テキストの Quill を担う。ナビメニューの項目は部品の行の中の入れ子の繰り返し入力（`initEditableRows()` の `placeholder` で行番号を `__NAV_INDEX__` にする。表示用の行は `RepeaterRows::fromInput()`/`fromModels()` で組み立てる）で、リンク先の種類に応じて入力欄を切り替える。部品の保存後に項目を同期するため、`syncSortableRows()` は送信された行のキー → 保存した id を返す。呼び出しコンテンツの選択肢の絞り込みは `call-contents.js` の `bindCallContentFields()` を使い、表示箇所は行の隠し input（`data-role="place-select"`、送信しない）で固定する。

## API

`GET /api/layout`（`API\LayoutController`）は `data.pages`（ページの種類ごとの `sidebar_position`: none/left/right と `show_breadcrumbs`）と `data.regions`（header/sidebar/footer ごとの部品の配列）を返す。部品は `block_type`・`title`・`subtitle` に加え、nav_menu は `items`（各項目は `label`・`path`・`prefix`（下の階層のページでも選択中にするか））、free_text は `content`、call_content は `call_content`（呼び出しコンテンツ API の 1 要素と同じ形）を持つ。サイトタイトル・SNS リンク・コピーライトはサイト設定 API の値で表示するため、部品には値を含めない。

chococo 側は `useSiteLayout()` で 1 回だけ取得し、`layouts/default.vue` がヘッダー（`LayoutHeader`）・フッター（`LayoutFooter`。見出しを持つ部品は上段に列、それ以外は下段に横並び）を、各ページが `LayoutSidebarFrame`（ページの種類とパンくずを渡す）で本文を包んでサイドバーとパンくず（`AppBreadcrumbs`。構造化データ BreadcrumbList も出す）を表示する（SSR ではレイアウトがページの setup より先に描画されるため、サイドバーの有無はページ側で決める）。
