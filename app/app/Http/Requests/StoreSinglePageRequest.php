<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSinglePageRequest extends FormRequest
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
            'header_image' => ['nullable', 'image', 'max:10240'],

            'details' => ['nullable', 'array'],
            'details.*.id' => ['nullable', 'integer', Rule::exists('single_page_details', 'id')],
            'details.*.sub_title' => ['required', 'string', 'max:255'],
            'details.*.contents' => ['nullable', 'string'],
            'details.*.sort_order' => ['nullable', 'integer'],
        ];
    }
}
