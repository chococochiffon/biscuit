<?php

namespace App\Http\Requests;

use App\Enums\BuilderComponentKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * コンポーネント(グローバル・独自)の登録・名前の変更(内容はエディタで編集する)。種類は登録するときだけ選ぶ。
 */
class StorePageBuilderComponentRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(BuilderComponentKind::class)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kind' => __('種類'),
            'name' => __('名前'),
            'description' => __('説明'),
        ];
    }
}
