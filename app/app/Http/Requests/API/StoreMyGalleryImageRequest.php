<?php

namespace App\Http\Requests\API;

use App\Http\Requests\StoreGalleryImageRequest;

/**
 * マイページからのギャラリーの画像の投稿(画像・名前・分類・コメント)。項目は管理画面の登録と同じ。
 */
class StoreMyGalleryImageRequest extends StoreGalleryImageRequest {}
