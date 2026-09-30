<?php

namespace App\View\Composers;

use App\Enums\ArticleApprovalStatus;
use App\Models\GalleryImage;
use Illuminate\View\View;

class PendingGalleryImageComposer
{
    /**
     * 承認待ち(未承認)のギャラリーの画像の件数をビューへ共有する(サイドメニューとギャラリー一覧のバッジに使う)。
     */
    public function compose(View $view): void
    {
        $view->with('pendingGalleryImageCount', GalleryImage::query()->withApproval(ArticleApprovalStatus::Pending)->count());
    }
}
