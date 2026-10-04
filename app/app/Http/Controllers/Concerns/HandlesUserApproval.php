<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ArticleApprovalStatus;
use App\Enums\AuditAction;
use App\Models\Article;
use App\Models\GalleryImage;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * マイページからユーザーが投稿するもの(記事・ギャラリーの画像)の承認の流れ。下書きで作り、申請で承認待ちにし、
 * 管理者が公開にする。承認を飛ばす権限(users.skip_approval)のあるユーザーは、申請でそのまま公開になり、
 * 公開中のものを変更しても公開中のまま。対象のモデルは HasApproval(approval・review_comment・changeApproval())を持つ。
 */
trait HandlesUserApproval
{
    /**
     * 下書きの承認を申請する(承認待ちにする。承認を飛ばす権限があれば、管理者が承認したときと同じくそのまま公開する)。
     * 前回の差し戻しの理由は、申請し直したら対応済みとみなして消す。下書きでなければ 422。
     */
    private function submitForApproval(Article|GalleryImage $model, User $user, string $notDraftMessage): void
    {
        $this->ensureApproval($model, ArticleApprovalStatus::Draft, $notDraftMessage);

        AuditLogger::updateWithLog($model, function () use ($model, $user) {
            $model->changeApproval($this->approvalOnSubmit($user))->fill(['review_comment' => null])->save();
        }, action: AuditAction::StatusChanged);
    }

    /**
     * 承認の申請を取り下げる(下書きに戻す)。承認待ちでなければ 422。
     */
    private function withdrawSubmission(Article|GalleryImage $model, string $notPendingMessage): void
    {
        $this->ensureApproval($model, ArticleApprovalStatus::Pending, $notPendingMessage);

        AuditLogger::updateWithLog($model, fn () => $model->changeApproval(ArticleApprovalStatus::Draft)->save(), action: AuditAction::StatusChanged);
    }

    /**
     * 公開中のものを変更したときは、管理者が承認し直すまで公開側に出さないよう承認待ちに戻す(保存はしない)。
     * 承認を飛ばす権限のあるユーザーのものは公開中のままにする。
     */
    private function backToPendingIfPublished(Article|GalleryImage $model, User $user): void
    {
        if ($model->approval === ArticleApprovalStatus::Published && ! $user->skip_approval) {
            $model->approval = ArticleApprovalStatus::Pending;
        }
    }

    /**
     * 承認を申請したときの公開ステータス(承認を飛ばす権限があれば公開、なければ承認待ち)。
     */
    private function approvalOnSubmit(User $user): ArticleApprovalStatus
    {
        return $user->skip_approval ? ArticleApprovalStatus::Published : ArticleApprovalStatus::Pending;
    }

    /**
     * 今の公開ステータスが $expected でなければ 422 にする(申請・取り下げの前の確認)。
     */
    private function ensureApproval(Article|GalleryImage $model, ArticleApprovalStatus $expected, string $message): void
    {
        if ($model->approval !== $expected) {
            throw ValidationException::withMessages(['approval' => $message]);
        }
    }

    /**
     * ログイン中のユーザー(マイページの API は auth:sanctum の中にある)。
     */
    private function user(Request $request): User
    {
        return $request->user();
    }
}
