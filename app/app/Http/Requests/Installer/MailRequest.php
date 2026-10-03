<?php

namespace App\Http\Requests\Installer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * インストーラーのメールの段(SMTP の設定と、試しのメールの宛先)。
 * パスワードは .env にシングルクォートで囲んで書くため、シングルクォートと改行だけは使えない。
 */
class MailRequest extends FormRequest
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
        return [
            'host' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9.\-]+\z/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', 'in:starttls,ssl'],
            'username' => ['nullable', 'string', 'max:255', 'regex:/\A[^\'\x00-\x1F\x7F]*\z/'],
            'password' => ['nullable', 'string', 'max:255', 'regex:/\A[^\'\x00-\x1F\x7F]*\z/'],
            'from_address' => ['required', 'email', 'max:255'],
            'test_to' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'host' => __('SMTP のホスト'),
            'port' => __('ポート'),
            'encryption' => __('暗号化'),
            'username' => __('SMTP のユーザー名'),
            'password' => __('SMTP のパスワード'),
            'from_address' => __('送信元のメールアドレス'),
            'test_to' => __('試しに送る宛先'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'host.regex' => __('SMTP のホストには、smtp.example.com のようなホスト名を入れてください。'),
            'username.regex' => __('シングルクォート(\')は使えません。'),
            'password.regex' => __('シングルクォート(\')は使えません。'),
        ];
    }
}
