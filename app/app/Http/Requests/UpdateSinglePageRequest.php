<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPath;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSinglePageRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'short_sentences' => ['required', 'string', 'max:255'],
            ...$this->pathRules(slugRequired: true, ignore: $this->route('singlePage')),
            'top_page_view' => ['nullable', 'boolean'],
            'link_list_view' => ['nullable', 'boolean'],
            'header_image' => ['nullable', 'image', 'max:10240'],
            'publication_start_datetime' => ['required', 'date_format:Y-m-d H:i'],
            'publication_end_datetime' => ['nullable', 'date_format:Y-m-d H:i', 'after:publication_start_datetime'],

            'details' => ['nullable', 'array'],
            'details.*.id' => ['nullable', 'integer', Rule::exists('single_page_details', 'id')],
            'details.*.sub_title' => ['required', 'string', 'max:255'],
            'details.*.contents' => ['nullable', 'string'],
            'details.*.sort_order' => ['nullable', 'integer'],
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
