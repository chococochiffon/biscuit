<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * コンポーネントの名前・説明の変更。種類は登録したあとは変えない(中身の形が種類で違うため)。
 */
class UpdatePageBuilderComponentRequest extends StorePageBuilderComponentRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_diff_key(parent::rules(), ['kind' => true]);
    }
}
