<?php

namespace App\Http\Requests\Auth;

use App\Models\Administrator;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdministratorLoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * メールアドレスとパスワードを確かめ、正しければ管理者を返す(まだログインはさせない。確認コードの入力のあとでログインする)。
     * 間違っていれば、ログイン失敗のイベント(監査ログのリスナーが記録する)を出して入力エラーにする。
     */
    public function validateCredentials(): Administrator
    {
        $guard = Auth::guard('admin');
        $credentials = $this->only('email', 'password');

        if (! $guard->validate($credentials)) {
            event(new Failed('admin', $guard->getLastAttempted(), $credentials));

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        return $guard->getLastAttempted();
    }
}
