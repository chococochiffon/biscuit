<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSinglePageRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'short_sentences' => ['required', 'string', 'max:255'],
            'taxonomy' => ['nullable', 'string', 'max:255'],
            'uri' => ['nullable', 'string', 'max:255'],
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
}
