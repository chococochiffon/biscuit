<?php

namespace App\Http\Requests\API;

/**
 * マイページの記事の更新。ルールは StoreMyArticleRequest と共通(投稿先は選び直したときだけ送る)。
 */
class UpdateMyArticleRequest extends StoreMyArticleRequest {}
