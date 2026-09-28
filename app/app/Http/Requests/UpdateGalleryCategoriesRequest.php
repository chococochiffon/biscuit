<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGalleryCategoriesRequest extends FormRequest
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
     * 分類の一覧(繰り返し入力の行)をまとめて受け取る。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'categories' => ['nullable', 'array'],
            'categories.*.id' => ['nullable', 'integer', Rule::exists('gallery_categories', 'id')->withoutTrashed()],
            'categories.*.name' => ['required', 'string', 'max:128'],
            'categories.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
