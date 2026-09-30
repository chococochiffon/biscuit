<?php

namespace App\Http\Requests\API;

use App\Http\Requests\StoreGalleryImageRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * マイページのギャラリーの画像の更新(名前・分類・コメント)。画像は POST /me/gallery-images/{id}/image で変える。
 */
class UpdateMyGalleryImageRequest extends StoreGalleryImageRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['image']);
    }
}
