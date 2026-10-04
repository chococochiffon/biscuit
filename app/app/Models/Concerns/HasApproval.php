<?php

namespace App\Models\Concerns;

use App\Enums\ArticleApprovalStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * 公開ステータス(approval。ArticleApprovalStatus)と差し戻しの理由(review_comment)を持つモデル(記事・ギャラリーの画像)用の共通処理。
 */
trait HasApproval
{
    /**
     * 公開ステータスを変える(保存はしない)。記事は、初めて公開したときの日時も決める独自の changeApproval() を持つ。
     */
    public function changeApproval(ArticleApprovalStatus $approval): static
    {
        $this->approval = $approval;

        return $this;
    }

    /**
     * 公開ステータスで絞り込む。
     */
    #[Scope]
    protected function withApproval(Builder $query, ArticleApprovalStatus $approval): void
    {
        $query->where('approval', $approval);
    }

    /**
     * 管理者に差し戻されたもの(差し戻しの理由がある下書き)に絞り込む。承認を申請し直すと理由は消える。
     */
    #[Scope]
    protected function returned(Builder $query): void
    {
        $query->where('approval', ArticleApprovalStatus::Draft)
            ->whereNotNull('review_comment')
            ->where('review_comment', '!=', '');
    }
}
