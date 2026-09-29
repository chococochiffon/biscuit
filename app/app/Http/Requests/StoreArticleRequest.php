<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPath;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    use ValidatesPath;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->preparePathInput();
    }

    /**
     * Get the validation rules that apply to the request.
     * 更新(UpdateArticleRequest)と共通のルール。一意性などのチェックでは、更新対象(ルートのモデル。新規登録時は null)を除く。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'thumbnail' => ['nullable', 'image', 'max:'.config('limits.image_max_kilobytes')],
            ...$this->pathRules(slugRequired: false, ignore: $this->route('article')),
            'publication_start_datetime' => ['required', 'date_format:Y-m-d H:i'],
            'publication_end_datetime' => ['nullable', 'date_format:Y-m-d H:i', 'after:publication_start_datetime'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->pathMessages();
    }
}
