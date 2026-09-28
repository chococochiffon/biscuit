<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateGalleryImageRequest extends StoreGalleryImageRequest
{
    /**
     * Get the validation rules that apply to the request.
     * 更新時は画像を任意にする(未指定なら登録済みの画像のまま)。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'image' => ['nullable', 'image', 'max:10240'],
        ];
    }
}
