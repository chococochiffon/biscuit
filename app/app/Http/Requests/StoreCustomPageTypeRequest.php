<?php

namespace App\Http\Requests;

use App\Enums\CustomPageBaseType;
use App\Models\CustomPageType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreCustomPageTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * カスタム名をテーブル名の元にする単数形の snake_case にそろえてから検証する(例: Recipes → recipe)。
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => CustomPageType::normalizeName($this->input('name'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     * カスタム名は削除済みの種類とも重複させない(削除してもテーブルを残すため)。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('custom_page_types', 'name')],
            'label' => ['required', 'string', 'max:128'],
            'base_type' => ['required', new Enum(CustomPageBaseType::class)],
        ];
    }

    /**
     * 登録件数の上限と、作るテーブルが既にないことを確認する。
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (CustomPageType::count() >= config('limits.custom_page_types')) {
                    $validator->errors()->add('name', __('カスタムページは:max件まで登録できます。', ['max' => config('limits.custom_page_types')]));

                    return;
                }

                if ($validator->errors()->hasAny(['name', 'base_type'])) {
                    return;
                }

                $type = new CustomPageType(['name' => $this->input('name'), 'base_type' => $this->input('base_type')]);
                $existing = array_filter($type->tableNames(), fn (string $table) => Schema::hasTable($table));

                if ($existing !== []) {
                    $validator->errors()->add('name', __('このカスタム名のテーブル(:tables)は既にあるため使えません。', ['tables' => implode(', ', $existing)]));
                }
            },
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
            'name' => __('カスタム名'),
            'label' => __('表示名'),
            'base_type' => __('型'),
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
            'name.regex' => __('カスタム名は半角英小文字で始まり、半角英小文字・数字・アンダースコアで入力してください。'),
        ];
    }
}
