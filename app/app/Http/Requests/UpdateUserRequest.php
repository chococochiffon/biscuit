<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

/**
 * 更新時のバリデーション。ルールは StoreUserRequest と共通(差分だけをここで上書きする)。
 */
class UpdateUserRequest extends StoreUserRequest
{
    /**
     * 更新時のパスワードは、変更する場合だけ入力する。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
        ]);
    }
}
