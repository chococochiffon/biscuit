---
paths:
  - app/app/Support/ImageResizer.php
  - app/app/Models/SiteSetting.php
  - app/app/Models/TopSliderImage.php
  - app/app/Models/SocialLink.php
  - app/app/Models/UserDetail.php
  - app/app/Models/UserSkill.php
  - app/app/Models/SinglePage.php
  - app/app/Enums/SocialService.php
  - app/app/Http/Controllers/SiteSettingController.php
  - app/app/Http/Controllers/UserController.php
  - app/app/Http/Controllers/SinglePageController.php
  - app/app/Http/Controllers/ArticleController.php
  - app/app/Http/Controllers/API/SiteSettingController.php
  - app/app/Http/Requests/*SiteSettingRequest.php
  - app/app/Http/Requests/*UserRequest.php
  - app/app/Http/Resources/SiteSettingResource.php
  - app/app/Http/Resources/TopSliderImageResource.php
  - app/app/Http/Resources/SocialLinkResource.php
  - app/app/Http/Resources/UserDetailResource.php
  - app/app/Http/Resources/UserSkillResource.php
  - app/resources/views/admin/site_settings/**
  - app/resources/views/admin/users/**
  - app/resources/js/admin.js
  - app/database/seeders/TopSliderImageSeeder.php
  - app/tests/Feature/**/SiteSetting*
  - app/tests/Feature/**/UserControllerTest.php
---

# 画像保存と埋め込みリピーター

## 画像の保存

- `SinglePage`（ヘッダー画像）・`SiteSetting`（サイトアイコン・サイト画像）・`UserDetail`（`User` と 1 対 1、`User::detail()`）は、画像を `public` ディスクへ `年月日時分秒_テーブル名_id` 命名で保存する共通パターンを持つ。
- サイト設定の `site_image` は OGP 用。公開側トップのスライダー画像は別テーブル `TopSliderImage`（`top_image`・リンク先 `url`（任意）・`sort_order`）で管理する。
- スライダー画像は管理画面で Cropper.js（`admin.js` の `initImageCroppers()`）により 16:9 の切り抜き範囲を選ぶ。範囲（`crop_x`/`crop_y`/`crop_width`/`crop_height`、元画像のピクセル基準。未指定なら中央）で切り抜いて 1920×1080 に縮小し、`image/top_image` にランダムな文字列のファイル名で保存する。
- 画像の切り抜き・縮小は記事サムネイルと共通の `Support\ImageResizer`（GD）を使う。

## 埋め込みリピーター

- 次の子レコードは専用の管理画面を持たず、親のフォームに埋め込んで編集する。
  - SNS リンク（`SocialLink`、サービス種別は `SocialService` enum）：サイト全体で共通のレコード。サイト設定の作成/編集フォームに埋め込む。
  - トップスライダー画像（`TopSliderImage`）：サイト設定の作成/編集フォームに埋め込む。
  - ユーザー詳細のスキル（`UserSkill`、習熟度 0〜100）：ユーザーの作成/編集フォームに埋め込む。
- 行の追加・削除・並び替えは `admin.js` の汎用リピーター `initRepeaterRows()`（`data-role="repeater"`）で行う。
- `GET /api/site-setting` は `social_links`・`top_slider_images` を並び順で含む。
