<?php

namespace App\Http\Requests;

use App\Enums\UserDetailNameSetting;
use App\Models\UserSkill;
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
            'user_detail.skills' => ['nullable', 'array'],
            // 既存のスキルは、このユーザーの詳細に紐づくものだけ更新できる
            'user_detail.skills.*.id' => [
                'nullable', 'integer',
                Rule::exists('user_skills', 'id')->where('user_detail_id', $this->route('user')?->detail?->id),
            ],
            'user_detail.skills.*.name' => ['required', 'string', 'max:255'],
            'user_detail.skills.*.level' => ['required', 'integer', 'min:0', 'max:'.UserSkill::MAX_LEVEL],
            'user_detail.skills.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
