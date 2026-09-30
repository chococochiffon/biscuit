<?php

namespace App\Http\Requests\API;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * 招待の受諾(招待のリンクのトークン・メールアドレスと、アカウント名・パスワード・ユーザー詳細・スキル)。
 * プロフィールの項目は管理画面のユーザー登録と同じルールで、メールアドレスは招待のものから変えられない。
 * アイコン画像はログイン後にマイページで登録する。承認を飛ばす権限は招待のときに管理者が決めたまま。
 */
class AcceptInvitationRequest extends StoreUserRequest
{
    private ?UserInvitation $invitation = null;

    /**
     * リンクのトークンとメールアドレスで見つかった、まだ使える招待(なければ null)。
     */
    public function invitation(): ?UserInvitation
    {
        return $this->invitation ??= UserInvitation::findUsable((string) $this->input('email'), (string) $this->input('token'));
    }

    protected function targetUser(): ?User
    {
        return $this->invitation()?->user;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...Arr::except(parent::rules(), [
                'email',
                'skip_approval',
                'user_detail.user_image',
                'user_detail.user_image_crop',
                'user_detail.user_image_crop.x',
                'user_detail.user_image_crop.y',
                'user_detail.user_image_crop.width',
                'user_detail.user_image_crop.height',
            ]),
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ];
    }
}
