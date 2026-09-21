<?php

namespace App\Http\Requests;

use App\Enums\UserDetailNameSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user'))->withoutTrashed(),
            ],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],

            'user_detail' => ['required', 'array'],
            'user_detail.first_name' => ['required', 'string', 'max:255'],
            'user_detail.family_name' => ['required', 'string', 'max:255'],
            'user_detail.nick_name' => ['required', 'string', 'max:255'],
            'user_detail.birthday' => ['required', 'date'],
            'user_detail.user_image' => ['nullable', 'image', 'max:10240'],
            'user_detail.comment' => ['nullable', 'string'],
            'user_detail.view_flag' => ['nullable', 'boolean'],
            'user_detail.name_settings' => ['required', new Enum(UserDetailNameSetting::class)],
        ];
    }
}
