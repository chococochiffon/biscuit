<?php

namespace App\Http\Requests\API;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * マイページの記事のサムネイル画像の変更(管理画面と同じく、比率が近い保存サイズへ中央で切り抜いて縮小する)。
 */
class UpdateMyArticleThumbnailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'thumbnail' => ['required', 'image', 'max:'.config('limits.image_max_kilobytes')],
        ];
    }
}
