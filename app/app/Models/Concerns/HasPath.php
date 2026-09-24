<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * 公開側URLのパス(path)を、親パス(parent_path)とスラッグ(slug)から組み立てて保存するモデル用の共通処理。
 * スラッグが未入力(null)の場合は id を使う(例: /news/123)。数字だけのスラッグは id 用に予約しているため入力させない。
 */
trait HasPath
{
    /**
     * スラッグ(URLの1階層分)に使える書式。半角英小文字・数字をハイフンでつないだもの。
     */
    public const SLUG_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    /**
     * 保存のたびに path を組み立てる。スラッグ未入力の新規作成時は id が決まっていないため、作成直後に組み立て直す。
     */
    public static function bootHasPath(): void
    {
        static::saving(function (Model $model) {
            $model->path = $model->pathSlug() !== null ? self::buildPath($model->parent_path, $model->pathSlug()) : null;
        });

        static::created(function (Model $model) {
            if ($model->path === null) {
                $model->path = self::buildPath($model->parent_path, $model->pathSlug());
                $model->saveQuietly();
            }
        });
    }

    /**
     * 親パスとスラッグから公開側URLのパスを組み立てる(例: company + about → /company/about、親パスなし → /about)。
     */
    public static function buildPath(?string $parentPath, string $slug): string
    {
        return '/'.ltrim(trim((string) $parentPath, '/').'/'.$slug, '/');
    }

    /**
     * パスの末尾に使う値(スラッグ、未入力なら id。id も未確定なら null)。
     */
    public function pathSlug(): ?string
    {
        return $this->slug ?? ($this->getKey() !== null ? (string) $this->getKey() : null);
    }
}
