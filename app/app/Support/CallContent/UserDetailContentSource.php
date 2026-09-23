<?php

namespace App\Support\CallContent;

use App\Models\UserDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * 呼び出しコンテンツ(CallContent)がUserDetailを参照する場合の実データ取得を担う。
 */
class UserDetailContentSource
{
    /**
     * リンクリスト表示用に、表示対象(view_flag=true)のユーザー詳細を指定件数取得する。
     *
     * @return Collection<int, UserDetail>
     */
    public function getLinkList(int $count): Collection
    {
        return $this->viewableQuery()->take($count)->get();
    }

    /**
     * スキルリスト表示用に、表示対象(view_flag=true)のユーザー詳細を指定件数取得する。
     *
     * @return Collection<int, UserDetail>
     */
    public function getSkillList(int $count): Collection
    {
        return $this->viewableQuery()->take($count)->get();
    }

    /**
     * @return Builder<UserDetail>
     */
    private function viewableQuery(): Builder
    {
        return UserDetail::query()->where('view_flag', true)->latest();
    }
}
