<?php

namespace App\Http\Requests;

use App\Enums\ArticleApprovalStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Enum;

/**
 * 更新時のバリデーション。ルールは StoreArticleRequest と共通(差分だけをここで上書きする)。
 */
class UpdateArticleRequest extends StoreArticleRequest
{
    /**
     * 更新時は公開設定(approval)も受け付ける。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'approval' => ['required', new Enum(ArticleApprovalStatus::class)],
        ];
    }
}
