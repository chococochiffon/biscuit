<?php

namespace App\Http\Requests\Installer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * インストーラーのサイトの段。URL は http(s) で、末尾の / やパスを付けない(公開側・管理画面のどちらも、そのサイトのいちばん上の URL)。
 */
class SiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $url = ['required', 'string', 'max:255', 'regex:#\Ahttps?://[A-Za-z0-9.\-]+(:\d{1,5})?/?\z#'];

        return [
            'site_title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'locale' => ['required', Rule::in(config('app.available_locales'))],
            'timezone' => ['required', 'timezone:all'],
            'front_url' => $url,
            'admin_url' => [...$url, 'different:front_url'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'site_title' => __('サイト名'),
            'description' => __('サイトの説明'),
            'locale' => __('管理画面の言語'),
            'timezone' => __('タイムゾーン'),
            'front_url' => __('公開側の URL'),
            'admin_url' => __('管理画面の URL'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'front_url.regex' => __('公開側の URL は、https://example.com のように http(s):// で始まるサイトの URL を入れてください(パスは付けません)。'),
            'admin_url.regex' => __('管理画面の URL は、https://admin.example.com のように http(s):// で始まるサイトの URL を入れてください(パスは付けません)。'),
            'admin_url.different' => __('公開側と管理画面には、別の URL(別のドメインかポート)を使います。'),
        ];
    }
}
