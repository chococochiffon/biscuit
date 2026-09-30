<?php

namespace App\Http\Requests;

use App\Enums\ArticleApprovalStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Enum;

class UpdateGalleryImageRequest extends StoreGalleryImageRequest
{
    /**
     * Get the validation rules that apply to the request.
     * 更新時は画像を任意にする(未指定なら登録済みの画像のまま)。公開ステータス(approval)と、
     * ユーザーの画像を差し戻すときの理由(review_comment)も受け付ける。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'image' => ['nullable', 'image', 'max:'.config('limits.image_max_kilobytes')],
            'approval' => ['required', new Enum(ArticleApprovalStatus::class)],
            'review_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
