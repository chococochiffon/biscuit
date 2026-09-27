<?php

namespace App\Models\Concerns;

use App\Support\ImageResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * 画像を公開ディスク(public)へ保存し、その公開URLを返すモデル用の共通処理。
 */
trait HasPublicImages
{
    /**
     * 公開ディスク基準のパスから公開URLを返す(パスが空なら null)。
     */
    public static function publicImageUrl(?string $path): ?string
    {
        return filled($path) ? Storage::disk('public')->url($path) : null;
    }

    /**
     * 指定ディレクトリへ、命名規則(yyyymmddhhmmss_テーブル名_id.拡張子)に従って画像を保存し、公開ディスク基準の保存パスを返す。
     * サイズ([幅, 高さ])を指定した場合は、指定範囲(未指定なら中央)で切り抜いてそのサイズへ拡大・縮小する。
     *
     * @param  array{int, int}|null  $size
     * @param  array{x: int|float, y: int|float, width: int|float, height: int|float}|null  $crop
     */
    protected function storeNamedImage(UploadedFile $file, string $directory, ?array $size = null, ?array $crop = null): string
    {
        $basename = now()->format('YmdHis').'_'.$this->getTable().'_'.$this->id;

        if ($size === null) {
            return $file->storeAs($directory, $basename.'.'.$file->extension(), 'public');
        }

        return self::storeResizedImage($file, $directory.'/'.$basename, $size[0], $size[1], $crop);
    }

    /**
     * 画像を指定範囲(未指定なら中央)で切り抜いて指定サイズへ拡大・縮小し、
     * 拡張子を除いた保存パスに拡張子を付けて保存する。公開ディスク基準の保存パスを返す。
     *
     * @param  array{x: int|float, y: int|float, width: int|float, height: int|float}|null  $crop
     */
    protected static function storeResizedImage(UploadedFile $file, string $pathWithoutExtension, int $width, int $height, ?array $crop = null): string
    {
        $extension = ImageResizer::extensionFor($file);
        $path = $pathWithoutExtension.'.'.$extension;

        Storage::disk('public')->put(
            $path,
            ImageResizer::cropAndResize($file->getRealPath(), $extension, $width, $height, $crop)
        );

        return $path;
    }
}
