<?php

namespace App\Http\Requests\API;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * マイページのプロフィール(名前・メールアドレス・ユーザー詳細・スキル)の更新。
 * 管理画面のユーザー編集と同じルールで、対象はログイン中のユーザー。
 * パスワード(PUT /me/password)とアイコン画像(POST /me/profile/image)は別の API で変更する。承認を飛ばす権限(skip_approval)は管理者だけが変えられる。
 */
class UpdateMyProfileRequest extends StoreUserRequest
{
    protected function targetUser(): ?User
    {
        return $this->user();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), [
            'password',
            'skip_approval',
            'user_detail.user_image',
            'user_detail.user_image_crop',
            'user_detail.user_image_crop.x',
            'user_detail.user_image_crop.y',
            'user_detail.user_image_crop.width',
            'user_detail.user_image_crop.height',
        ]);
    }
}
