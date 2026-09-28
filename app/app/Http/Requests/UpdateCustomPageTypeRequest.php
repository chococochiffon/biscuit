<?php

namespace App\Http\Requests;

use App\Enums\CustomFormType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * カスタムページの種類の更新。カスタム名・ベースの型はテーブルが決まるため変更できず、
 * 表示名とカスタムフォームの項目定義(繰り返し入力の行)を受け取る(登録時とは項目が違うため StoreCustomPageTypeRequest を継承しない)。
 */
class UpdateCustomPageTypeRequest extends FormRequest
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
     * forms.*.options は選択肢を 1 行に 1 つ書いたもので、プルダウン・ラジオ・チェックボックスのときは必須。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $optionTypes = collect(CustomFormType::cases())->filter->hasOptions()->map->value->implode(',');

        return [
            'label' => ['required', 'string', 'max:128'],
            'forms' => ['nullable', 'array'],
            'forms.*.id' => ['nullable', 'integer', Rule::exists($this->route('customPageType')->formsTableName(), 'id')->withoutTrashed()],
            'forms.*.parts_name' => ['required', 'string', 'max:255'],
            'forms.*.customs_form_type' => ['required', new Enum(CustomFormType::class)],
            'forms.*.options' => ['nullable', 'required_if:forms.*.customs_form_type,'.$optionTypes, 'string', 'max:2000'],
            'forms.*.sort_order' => ['nullable', 'integer', 'min:0'],
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
            'label' => __('表示名'),
            'forms.*.parts_name' => __('項目名'),
            'forms.*.customs_form_type' => __('入力形式'),
            'forms.*.options' => __('選択肢'),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'forms.*.options.required_if' => __('プルダウン・ラジオ・チェックボックスは選択肢を入力してください。'),
        ];
    }
}
