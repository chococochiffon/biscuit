<?php

namespace App\Http\Requests\Installer;

use App\Installer\PasswordPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * インストーラーの管理者の段。パスワードは最低文字数・初期のパスワードやよく使われる値の禁止・メールアドレスや表示名と同じ値の禁止を確かめる。
 */
class AdministratorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:'.config('installer.admin_password_min_length'),
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! is_string($value)) {
                        return;
                    }

                    $email = Str::lower((string) $this->input('email'));

                    if (PasswordPolicy::isForbidden($value)) {
                        $fail(__('初期のパスワードやよく使われるパスワードのままです。セキュリティ保護のため変更してください。'));
                    } elseif (in_array(Str::lower($value), [$email, Str::before($email, '@'), Str::lower((string) $this->input('name'))], true)) {
                        $fail(__('パスワードに、メールアドレスや表示名と同じ値は使えません。'));
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('表示名'),
            'email' => __('メールアドレス'),
            'password' => __('パスワード'),
        ];
    }
}
