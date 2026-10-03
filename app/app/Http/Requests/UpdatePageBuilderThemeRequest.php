<?php

namespace App\Http\Requests;

use App\Support\Builder\CustomCss;
use App\Support\Builder\ThemeRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * ページビルダーのテーマ(色・フォント)の保存。色は #rrggbb、フォントは ThemeRegistry::FONTS のキー(空はサイトの既定)。
 */
class UpdatePageBuilderThemeRequest extends FormRequest
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
        $rules = [
            'heading_font' => ['nullable', Rule::in(array_keys(ThemeRegistry::FONTS))],
            'body_font' => ['nullable', Rule::in(array_keys(ThemeRegistry::FONTS))],
            'custom_css' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                foreach (CustomCss::errors($value) as $message) {
                    $fail($message);
                }
            }],
        ];

        foreach (array_keys(ThemeRegistry::COLORS) as $name) {
            $rules["colors.{$name}"] = ['required', 'regex:/\A#[0-9a-fA-F]{6}\z/'];
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...collect(ThemeRegistry::COLORS)->mapWithKeys(fn (array $color, string $name) => ["colors.{$name}" => __('色(:name)', ['name' => __($color['label'])])])->all(),
            'heading_font' => __('見出しのフォント'),
            'body_font' => __('本文のフォント'),
            'custom_css' => __('サイト共通の CSS'),
        ];
    }

    /**
     * 保存する値(色は小文字にそろえる)。サイト共通の CSS はスーパー管理者だけが変えられ、ほかの管理者の保存では今の値のまま。
     *
     * @return array{colors: array<string, string>, heading_font: string|null, body_font: string|null, custom_css?: string|null}
     */
    public function themeAttributes(): array
    {
        $attributes = [
            'colors' => array_map(strtolower(...), $this->validated('colors')),
            'heading_font' => $this->validated('heading_font'),
            'body_font' => $this->validated('body_font'),
        ];

        if (Gate::allows('edit-builder-css')) {
            $attributes['custom_css'] = CustomCss::normalize($this->validated('custom_css'));
        }

        return $attributes;
    }
}
