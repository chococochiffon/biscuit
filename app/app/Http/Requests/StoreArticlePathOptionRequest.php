<?php

namespace App\Http\Requests;

use App\Models\ArticlePathOption;
use App\Models\SinglePage;
use App\Rules\NotReservedByCustomPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreArticlePathOptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 親パスの前後の空白とスラッシュを取り除く(記事の親パスの入力と同じ)。
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_path' => trim((string) $this->input('parent_path'), ' /'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     * 更新(UpdateArticlePathOptionRequest)と共通のルール。親パスの書式は記事の親パスと同じで、
     * 一意性のチェックでは更新対象(ルートのモデル。新規登録時は null)を除く。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:128'],
            'parent_path' => [
                'required', 'string', 'max:255',
                'regex:#^'.SinglePage::SLUG_PATTERN.'(?:/'.SinglePage::SLUG_PATTERN.')*$#',
                new NotReservedByCustomPage,
                Rule::unique('article_path_options', 'parent_path')->ignore($this->route('articlePathOption'))->withoutTrashed(),
            ],
        ];
    }

    /**
     * 新規登録のときだけ、登録件数の上限(config/limits.php)を確かめる。
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->route('articlePathOption') === null && ArticlePathOption::count() >= config('limits.article_path_options')) {
                    $validator->errors()->add('label', __('投稿先は:max件まで登録できます。', ['max' => config('limits.article_path_options')]));
                }
            },
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
            'parent_path.regex' => __('親パスは半角英小文字・数字・ハイフンで入力し、階層は「/」で区切ってください。'),
        ];
    }
}
