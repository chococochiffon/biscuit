---
paths:
  - app/app/Support/ImageResizer.php
  - app/app/Models/Concerns/HasPublicImages.php
  - app/app/Models/Article.php
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
  - app/resources/views/admin/partials/_image_cropper_modal.blade.php
  - app/resources/css/admin.css
  - app/resources/js/admin.js
  - app/database/seeders/TopSliderImageSeeder.php
  - app/tests/Feature/**/SiteSetting*
  - app/tests/Feature/**/UserControllerTest.php
---

# 画像保存と埋め込みリピーター

## 画像の保存

- `SinglePage`（ヘッダー画像）・`SiteSetting`（サイトアイコン・サイト画像）・`UserDetail`（`User` と 1 対 1、`User::detail()`）は、画像を `public` ディスクへ `年月日時分秒_テーブル名_id` 命名で保存する共通パターンを持つ。
- 画像の保存と公開 URL はモデル用の共通トレイト `Models\Concerns\HasPublicImages` にまとめている。命名規則での保存は `storeNamedImage()`（サイズを渡すと切り抜き・縮小もする）、ランダム名などパスを自分で決める場合は `storeResizedImage()`、URL は `publicImageUrl()`。各モデルは表示用の URL アクセサ（`thumbnail_url`・`header_image_url`・`site_icon_url`・`site_image_url`・`user_image_url`・`top_image_url`）を持ち、ビュー・API Resource はこれを使う（`Storage::disk('public')->url()` を直接書かない）。デフォルト画像がある項目（記事サムネイル・サイトアイコン・サイト画像）のアクセサは未設定時にデフォルト画像の URL を返し、ない項目は null を返す。サイト設定の API は未設定なら（デフォルトではなく）null を返す。
- サイト設定の `site_image` は OGP 用。公開側トップのスライダー画像は別テーブル `TopSliderImage`（`top_image`・リンク先 `url`（任意）・`sort_order`）で管理する。
- スライダー画像とユーザー詳細のアイコン画像（`user_image`）は、管理画面のドロップゾーン（`data-role="image-cropper-dropzone"`、見た目は `image-dropzone` と共通）へのドラッグ&ドロップまたはクリックで画像を選ぶと共通の切り抜きモーダル（`admin.partials._image_cropper_modal`、1 画面に 1 つ include する）が開き、Cropper.js（`admin.js` の `initImageCroppers()`）で切り抜く範囲と画像の拡大・縮小（ボタン・マウスホイール）を調整する。枠の比率は入力欄の `data-output-width`/`data-output-height`（保存サイズ）から決まり、「決定」で範囲（元画像のピクセル基準）を隠し input に入れて切り抜き結果をプレビュー表示する。決定後は「切り抜きを編集」で開き直せ、最初の選択でモーダルを閉じるとファイルの選択を取り消す。範囲が未指定なら中央で切り抜く。
  - スライダー画像: 範囲は `top_slider_images[n][crop_x/crop_y/crop_width/crop_height]`。1920×1080 に縮小し、`image/top_image` にランダムな文字列のファイル名で保存する。
  - アイコン画像: 範囲は `user_detail[user_image_crop][x/y/width/height]`。250×250（`UserDetail::USER_IMAGE_SIZE`）に縮小し、`image/user` に `年月日時分秒_user_details_id` 命名で保存する。
- 画像の切り抜き・縮小は記事サムネイルと共通の `Support\ImageResizer`（GD）を使う。

## 埋め込みリピーター

- 次の子レコードは専用の管理画面を持たず、親のフォームに埋め込んで編集する。
  - SNS リンク（`SocialLink`、サービス種別は `SocialService` enum）：サイト全体で共通のレコード。サイト設定の作成/編集フォームに埋め込む。
  - トップスライダー画像（`TopSliderImage`）：サイト設定の作成/編集フォームに埋め込む。
  - ユーザー詳細のスキル（`UserSkill`、習熟度 0〜100）：ユーザーの作成/編集フォームに埋め込む。
- 行の追加・削除・並び替えは `admin.js` の汎用リピーター `initRepeaterRows()`（`data-role="repeater"`）で行う。
- `GET /api/site-setting` は `social_links`・`top_slider_images` を並び順で含む。
