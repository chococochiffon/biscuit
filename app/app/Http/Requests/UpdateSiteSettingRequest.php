<?php

namespace App\Http\Requests;

use App\Enums\CallContentPlace;
use App\Enums\CallType;
use App\Enums\SocialService;
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
            'front_url' => ['nullable', 'url:http,https', 'max:255'],
            'api_url' => ['nullable', 'url:http,https', 'max:255'],
            'site_icon' => ['nullable', 'image', 'max:10240'],
            'site_image' => ['nullable', 'image', 'max:10240'],

            'call_contents' => ['nullable', 'array'],
            'call_contents.*.id' => ['nullable', 'integer', Rule::exists('call_contents', 'id')],
            'call_contents.*.call_name' => ['required', 'string', 'max:255'],
            'call_contents.*.title' => ['nullable', 'string', 'max:255'],
            'call_contents.*.subtitle' => ['nullable', 'string', 'max:255'],
            'call_contents.*.place' => ['required', new Enum(CallContentPlace::class)],
            'call_contents.*.call_type' => ['required', new Enum(CallType::class), new ValidCallContentCombination('call_type')],
            'call_contents.*.content_model_relation_id' => ['required', 'integer', Rule::exists('content_model_relations', 'id'), new ValidCallContentCombination('content_model_relation_id')],
            'call_contents.*.view_count' => ['required', 'integer', 'min:1', new ValidCallContentCombination('view_count')],
            'call_contents.*.sort_order' => ['nullable', 'integer', 'min:0'],

            'social_links' => ['nullable', 'array'],
            'social_links.*.id' => ['nullable', 'integer', Rule::exists('social_links', 'id')],
            'social_links.*.service' => ['required', new Enum(SocialService::class)],
            'social_links.*.name' => ['required', 'string', 'max:255'],
            'social_links.*.url' => ['required', 'url:http,https', 'max:2048'],
            'social_links.*.sort_order' => ['nullable', 'integer', 'min:0'],

            'top_slider_images' => ['nullable', 'array'],
            'top_slider_images.*.id' => ['nullable', 'integer', Rule::exists('top_slider_images', 'id')],
            'top_slider_images.*.image' => ['required_without:top_slider_images.*.id', 'nullable', 'image', 'max:10240'],
            'top_slider_images.*.url' => ['nullable', 'url:http,https', 'max:255'],
            'top_slider_images.*.crop_x' => ['nullable', 'numeric', 'min:0'],
            'top_slider_images.*.crop_y' => ['nullable', 'numeric', 'min:0'],
            'top_slider_images.*.crop_width' => ['nullable', 'numeric', 'min:1'],
            'top_slider_images.*.crop_height' => ['nullable', 'numeric', 'min:1'],
            'top_slider_images.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
