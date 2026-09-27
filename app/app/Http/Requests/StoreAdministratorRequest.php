<?php

namespace App\Http\Requests;

use App\Enums\AdministratorRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class StoreAdministratorRequest extends FormRequest
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
     * 更新(UpdateAdministratorRequest)と共通のルール。一意性などのチェックでは、更新対象(ルートのモデル。新規登録時は null)を除く。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('administrators', 'email')->ignore($this->route('administrator'))->withoutTrashed(),
            ],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'role' => ['required', new Enum(AdministratorRole::class)],
        ];
    }
}
