<?php

namespace App\Http\Requests;

use App\Enums\CallContentPlace;
use App\Enums\CallType;
use App\Rules\ValidCallContentCombination;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateSiteSettingRequest extends FormRequest
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
            'site_title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'site_icon' => ['nullable', 'image', 'max:10240'],
            'site_image' => ['nullable', 'image', 'max:10240'],

            'call_contents' => ['nullable', 'array'],
            'call_contents.*.id' => ['nullable', 'integer', Rule::exists('call_contents', 'id')],
            'call_contents.*.call_name' => ['required', 'string', 'max:255'],
            'call_contents.*.place' => ['required', new Enum(CallContentPlace::class)],
            'call_contents.*.call_type' => ['required', new Enum(CallType::class), new ValidCallContentCombination('call_type')],
            'call_contents.*.content_model_relation_id' => ['required', 'integer', Rule::exists('content_model_relations', 'id'), new ValidCallContentCombination('content_model_relation_id')],
            'call_contents.*.view_count' => ['required', 'integer', 'min:1', new ValidCallContentCombination('view_count')],
            'call_contents.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
