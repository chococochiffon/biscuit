<?php

namespace App\Http\Requests\Installer;

use App\Installer\EnvironmentWriter;
use App\Installer\PasswordPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * インストーラーのデータベースの段。DB 名・ユーザーは英数字と _、ユーザーは root を使わない(DB 専用のユーザーを作るため)。
 * パスワードは最低文字数・配布物の初期値やよく使われる値の禁止・DB 名やユーザーと同じ値の禁止・.env で壊れる文字の禁止を確かめる。
 */
class DatabaseRequest extends FormRequest
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
            'database' => ['required', 'string', 'max:64', 'regex:/\A[A-Za-z0-9_]+\z/'],
            'username' => ['required', 'string', 'max:32', 'regex:/\A[A-Za-z0-9_]+\z/', 'not_in:root,ROOT,Root,mysql.sys,mysql.session'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:'.config('installer.db_password_min_length'),
                'max:128',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! is_string($value)) {
                        return;
                    }

                    if (PasswordPolicy::isForbidden($value)) {
                        $fail(__('初期のパスワードやよく使われるパスワードのままです。セキュリティ保護のため変更してください。'));
                    } elseif (in_array(strtolower($value), [strtolower((string) $this->input('database')), strtolower((string) $this->input('username'))], true)) {
                        $fail(__('パスワードに、データベース名やユーザー名と同じ値は使えません。'));
                    } elseif (preg_match(EnvironmentWriter::UNSAFE_VALUE_PATTERN, $value) === 1) {
                        $fail(__('パスワードに、空白と $ " \' \\ ` # は使えません。'));
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
            'database' => __('データベース名'),
            'username' => __('ユーザー名'),
            'password' => __('パスワード'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'database.regex' => __('データベース名には、英数字と _ だけを使えます。'),
            'username.regex' => __('ユーザー名には、英数字と _ だけを使えます。'),
            'username.not_in' => __('ユーザー名に root は使えません(Biscuit 専用のユーザーを作ります)。'),
        ];
    }
}
