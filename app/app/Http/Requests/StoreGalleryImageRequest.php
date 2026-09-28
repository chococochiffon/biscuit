<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGalleryImageRequest extends FormRequest
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
     * 更新(UpdateGalleryImageRequest)と共通のルール。新規登録では画像を必須にする。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'gallery_category_id' => ['nullable', 'integer', Rule::exists('gallery_categories', 'id')->withoutTrashed()],
            'image' => ['required', 'image', 'max:10240'],
            'name' => ['required', 'string', 'max:128'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
