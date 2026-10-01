<?php

namespace App\Models\Concerns;

/**
 * JSON のカラムを、日本語と / をエスケープせずに読めるまま保存するモデル用の共通処理(ページビルダーの内容)。
 * メディア状況(MediaStatsService)の未使用の画像の判定は、行の値の「image/…」を拾うため、画像のパスがエスケープされていると見落とす。
 */
trait StoresReadableJson
{
    /**
     * @param  mixed  $value
     * @param  int  $flags
     */
    protected function asJson($value, $flags = 0): string|false
    {
        return parent::asJson($value, $flags | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
