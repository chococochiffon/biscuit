<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ArticleApprovalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * マイページからユーザーが投稿するもの(記事・ギャラリーの画像)の承認の流れ。下書きで作り、申請で承認待ちにし、
 * 管理者が公開にする。承認を飛ばす権限(users.skip_approval)のあるユーザーは、申請でそのまま公開になり、
 * 公開中のものを変更しても公開中のまま。対象のモデルは approval(ArticleApprovalStatus)を持つ。
 */
trait HandlesUserApproval
{
    /**
     * 承認を申請したときの公開ステータス(承認を飛ばす権限があれば公開、なければ承認待ち)。
     */
    private function approvalOnSubmit(User $user): ArticleApprovalStatus
    {
        return $user->skip_approval ? ArticleApprovalStatus::Published : ArticleApprovalStatus::Pending;
    }

    /**
     * 公開中のものを変更したときは、管理者が承認し直すまで公開側に出さないよう承認待ちに戻す(保存はしない)。
     * 承認を飛ばす権限のあるユーザーのものは公開中のままにする。
     */
    private function backToPendingIfPublished(Model $model, User $user): void
    {
        if ($model->approval === ArticleApprovalStatus::Published && ! $user->skip_approval) {
            $model->approval = ArticleApprovalStatus::Pending;
        }
    }

    /**
     * 今の公開ステータスが $expected でなければ 422 にする(申請・取り下げの前の確認)。
     */
    private function ensureApproval(Model $model, ArticleApprovalStatus $expected, string $message): void
    {
        if ($model->approval !== $expected) {
            throw ValidationException::withMessages(['approval' => $message]);
        }
    }
}
