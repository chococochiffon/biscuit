<?php

namespace App\View\Composers;

use App\Services\AnnouncementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AnnouncementNoticeComposer
{
    public function __construct(private AnnouncementService $announcements) {}

    /**
     * Biscuit からの重要・セキュリティのお知らせを、管理画面の上部のお知らせへ共有する(ログインしているすべての管理者)。
     * 画面を開くたびに外へ問い合わせないよう、キャッシュにある結果だけを見る(ダッシュボードと毎日のコマンドがキャッシュを作る)。
     */
    public function compose(View $view): void
    {
        $view->with('urgentAnnouncements', Auth::guard('admin')->check() ? $this->announcements->urgent(fetch: false) : []);
    }
}
