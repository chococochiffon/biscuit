<?php

namespace App\View\Composers;

use App\Services\UpdateCheckService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UpdateNoticeComposer
{
    public function __construct(private UpdateCheckService $updates) {}

    /**
     * 更新できる Biscuit のバージョンを、スーパー管理者のときだけ管理画面の上部のお知らせへ共有する。
     * 画面を開くたびに GitHub へ問い合わせないよう、キャッシュにある結果だけを見る(ダッシュボードと毎日のコマンドがキャッシュを作る)。
     */
    public function compose(View $view): void
    {
        $administrator = Auth::guard('admin')->user();

        $view->with('updateNotice', $administrator?->can('view-system-status') ? $this->updates->availableUpdate(fetch: false) : null);
    }
}
