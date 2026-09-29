<?php

namespace App\Http\Requests;

use App\Enums\UserDetailNameSetting;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 更新対象のユーザー(ルートのモデル。新規登録時は null)。マイページのプロフィール更新ではログイン中のユーザーにする。
     */
    protected function targetUser(): ?User
    {
        return $this->route('user');
    }

    /**
     * Get the validation rules that apply to the request.
     * 更新(UpdateUserRequest)と共通のルール。一意性などのチェックでは、更新対象(ルートのモデル。新規登録時は null)を除く。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->targetUser())->withoutTrashed(),
            ],
            'password' => ['required', 'string', Password::default(), 'confirmed'],

            'user_detail' => ['required', 'array'],
            'user_detail.first_name' => ['required', 'string', 'max:255'],
            'user_detail.family_name' => ['required', 'string', 'max:255'],
            'user_detail.nick_name' => ['required', 'string', 'max:255'],
            'user_detail.birthday' => ['required', 'date'],
            'user_detail.user_image' => ['nullable', 'image', 'max:10240'],
            // アイコン画像の切り抜き範囲(元画像のピクセル基準。未指定なら中央で切り抜く)
            'user_detail.user_image_crop' => ['nullable', 'array'],
            'user_detail.user_image_crop.x' => ['nullable', 'numeric', 'min:0'],
            'user_detail.user_image_crop.y' => ['nullable', 'numeric', 'min:0'],
            'user_detail.user_image_crop.width' => ['nullable', 'numeric', 'min:1'],
            'user_detail.user_image_crop.height' => ['nullable', 'numeric', 'min:1'],
            'user_detail.comment' => ['nullable', 'string'],
            'user_detail.view_flag' => ['nullable', 'boolean'],
            'user_detail.name_settings' => ['required', new Enum(UserDetailNameSetting::class)],
            'user_detail.skills' => ['nullable', 'array'],
            // 既存のスキルは、このユーザーの詳細に紐づくものだけ更新できる(新規登録時は既存のスキルを指定できない)
            'user_detail.skills.*.id' => [
                'nullable', 'integer',
                Rule::exists('user_skills', 'id')->where('user_detail_id', $this->targetUser()?->detail?->id),
            ],
            'user_detail.skills.*.name' => ['required', 'string', 'max:255'],
            'user_detail.skills.*.level' => ['required', 'integer', 'min:'.UserSkill::MIN_LEVEL, 'max:'.UserSkill::MAX_LEVEL],
            'user_detail.skills.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
