<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 登録件数の上限
    |--------------------------------------------------------------------------
    |
    | 管理画面の繰り返し入力で登録できる件数の上限。管理画面からは変更せず、
    | 変更する場合は .env の値を書き換える。
    |
    */

    // トップスライダー画像の最大枚数
    'top_slider_images' => (int) env('LIMIT_TOP_SLIDER_IMAGES', 5),

    // 固定ページ 1 件あたりの詳細の最大件数
    'single_page_details' => (int) env('LIMIT_SINGLE_PAGE_DETAILS', 10),

    // Q&A の質問 1 件あたりの回答(分岐)の最大件数
    'question_answers' => (int) env('LIMIT_QUESTION_ANSWERS', 10),

    // カスタムページの種類の最大件数
    'custom_page_types' => (int) env('LIMIT_CUSTOM_PAGE_TYPES', 5),

    // ユーザーが記事を投稿するときに選ぶ投稿先の最大件数
    'article_path_options' => (int) env('LIMIT_ARTICLE_PATH_OPTIONS', 10),

    // レイアウトのナビメニューの部品 1 件あたりの項目の最大件数
    'layout_nav_items' => (int) env('LIMIT_LAYOUT_NAV_ITEMS', 15),

    // ページビルダーの 1 ページあたりのブロックの最大数
    'builder_nodes' => (int) env('LIMIT_BUILDER_NODES', 500),

    // ページビルダーのエディタの「版の履歴」に出す版の数(新しい順。古い版も消さずに残す)
    'builder_versions' => (int) env('LIMIT_BUILDER_VERSIONS', 50),

    // ページビルダーで読み込めるファイル(書き出した JSON。画像を含む)の最大サイズ(KB)。php.ini の upload_max_filesize・post_max_size もこれ以上にしておく
    'builder_import_kilobytes' => (int) env('LIMIT_BUILDER_IMPORT_KILOBYTES', 10240),

    /*
    |--------------------------------------------------------------------------
    | アップロードできる画像のサイズ
    |--------------------------------------------------------------------------
    |
    | 管理画面・マイページでアップロードできる画像 1 枚の最大サイズ(KB)。
    | php.ini の upload_max_filesize・post_max_size もこれ以上にしておく。
    |
    */

    'image_max_kilobytes' => (int) env('LIMIT_IMAGE_MAX_KILOBYTES', 10240),

    /*
    |--------------------------------------------------------------------------
    | 一覧の 1 ページの件数
    |--------------------------------------------------------------------------
    |
    | 管理画面の一覧と、公開側の記事一覧 API で 1 ページに表示する件数。
    |
    */

    // 管理画面の一覧(記事・固定ページ・タグ・ユーザー・管理者・Q&A・データ種別紐付け)
    'admin_per_page' => (int) env('LIMIT_ADMIN_PER_PAGE', 20),

    // 記事一覧 API(GET /api/articles)
    'api_per_page' => (int) env('LIMIT_API_PER_PAGE', 20),

];
