<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ページビルダーのテンプレートの保存(エディタの「今のページをテンプレートとして保存」)。
 * 内容の検証と整形は、下書きの保存(SavePageBuilderRequest)と同じく BuilderValidator で行う。
 */
class StorePageBuilderTemplateRequest extends SavePageBuilderRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'array'],
        ];
    }
}
