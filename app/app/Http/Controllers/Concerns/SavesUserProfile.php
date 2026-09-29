<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\StoreUserRequest;
use App\Models\UserDetail;
use App\Support\AuditLogger;
use App\Support\SyncedRows;

/**
 * ユーザーのプロフィール(ユーザー詳細とスキル)の保存処理。管理画面のユーザー管理と、chococo のマイページの API で共通に使う。
 */
trait SavesUserProfile
{
    use SyncsSortableRows;

    /**
     * 監査ログの変更内容に、ユーザー本体の列と並べて残すユーザー詳細の値(項目名は detail. から始める)。
     *
     * @return array<string, string|null>
     */
    private function auditDetail(?UserDetail $detail): array
    {
        if ($detail === null) {
            return [];
        }

        return collect(AuditLogger::snapshot($detail))
            ->except('user_id')
            ->mapWithKeys(fn (?string $value, string $key) => ["detail.{$key}" => $value])
            ->all();
    }

    /**
     * リクエストからuser_detailの保存用属性を組み立てる(画像は別途保存する)。
     *
     * @return array<string, mixed>
     */
    private function userDetailAttributes(StoreUserRequest $request): array
    {
        return [
            'first_name' => $request->validated('user_detail.first_name'),
            'family_name' => $request->validated('user_detail.family_name'),
            'nick_name' => $request->validated('user_detail.nick_name'),
            'birthday' => $request->validated('user_detail.birthday'),
            'comment' => $request->validated('user_detail.comment'),
            'view_flag' => $request->boolean('user_detail.view_flag'),
            'name_settings' => $request->validated('user_detail.name_settings'),
        ];
    }

    /**
     * フォームから送信されたスキル(user_detail.skills)の内容に、ユーザー詳細のスキルを同期する。
     * (作成/更新/削除と並び順の扱いは SyncsSortableRows::syncSortableRows() を参照)
     *
     * @param  array<int, array{id?: int|string|null, name: string, level: int|string, sort_order?: int|string|null}>  $rows
     */
    private function syncSkills(UserDetail $detail, array $rows): SyncedRows
    {
        return $this->syncSortableRows($detail->skills(), $rows, fn (array $row) => [
            'name' => $row['name'],
            'level' => $row['level'],
        ]);
    }
}
