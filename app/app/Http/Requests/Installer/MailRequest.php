<?php

namespace App\Http\Requests\Installer;

use App\Installer\MailInstaller;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * インストーラーのメールの段(送り方(SMTP か Resend)・その設定と、試しのメールの宛先)。
 * SMTP の項目は SMTP のときだけ、Resend の API キーは Resend のときだけ受け取る。
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
            'driver' => ['required', Rule::in(MailInstaller::DRIVERS)],
            'host' => ['exclude_unless:driver,smtp', 'required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9.\-]+\z/'],
            'port' => ['exclude_unless:driver,smtp', 'required', 'integer', 'between:1,65535'],
            'encryption' => ['exclude_unless:driver,smtp', 'required', 'in:starttls,ssl'],
            'username' => ['exclude_unless:driver,smtp', 'nullable', 'string', 'max:255', 'regex:/\A[^\'\x00-\x1F\x7F]*\z/'],
            'password' => ['exclude_unless:driver,smtp', 'nullable', 'string', 'max:255', 'regex:/\A[^\'\x00-\x1F\x7F]*\z/'],
            // 空のときは、Resend を設定済みなら今の API キーを使う(コントローラーで補う)
            'api_key' => ['exclude_unless:driver,resend', 'nullable', 'string', 'max:255', 'regex:/\Are_[A-Za-z0-9_\-]+\z/'],
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
            'driver' => __('メールの送り方'),
            'host' => __('SMTP のホスト'),
            'port' => __('ポート'),
            'encryption' => __('暗号化'),
            'username' => __('SMTP のユーザー名'),
            'password' => __('SMTP のパスワード'),
            'api_key' => __('Resend の API キー'),
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
            'api_key.regex' => __('Resend の API キー(re_ で始まる文字列)を入れてください。'),
        ];
    }
}
