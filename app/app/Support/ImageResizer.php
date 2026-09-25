<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class ImageResizer
{
    /**
     * 加工後もそのままの形式で保存できる拡張子。これ以外は png で保存する。
     */
    public const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * アップロードされた画像の保存時の拡張子を返す。
     */
    public static function extensionFor(UploadedFile $file): string
    {
        return in_array($file->extension(), self::SUPPORTED_EXTENSIONS, true) ? $file->extension() : 'png';
    }

    /**
     * 画像の指定範囲(未指定なら中央を目標サイズの比率で)を切り抜いてから目標サイズへ拡大・縮小し、
     * 指定形式でエンコードしたバイナリを返す。
     * 指定範囲は元画像のピクセル基準で、画像からはみ出す分は画像内に収まるよう丸める。
     *
     * @param  array{x: int|float, y: int|float, width: int|float, height: int|float}|null  $crop
     */
    public static function cropAndResize(string $sourcePath, string $extension, int $width, int $height, ?array $crop = null): string
    {
        $source = imagecreatefromstring(file_get_contents($sourcePath));
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($crop !== null) {
            $cropX = max(0, min($sourceWidth - 1, (int) round($crop['x'])));
            $cropY = max(0, min($sourceHeight - 1, (int) round($crop['y'])));
            $cropWidth = max(1, min($sourceWidth - $cropX, (int) round($crop['width'])));
            $cropHeight = max(1, min($sourceHeight - $cropY, (int) round($crop['height'])));
        } else {
            // 目標の比率になるよう、はみ出す辺を中央基準で切り落とす
            $cropWidth = min($sourceWidth, (int) round($sourceHeight * $width / $height));
            $cropHeight = min($sourceHeight, (int) round($sourceWidth * $height / $width));
            $cropX = intdiv($sourceWidth - $cropWidth, 2);
            $cropY = intdiv($sourceHeight - $cropHeight, 2);
        }

        $resized = imagecreatetruecolor($width, $height);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $source, 0, 0, $cropX, $cropY, $width, $height, $cropWidth, $cropHeight);

        ob_start();
        match ($extension) {
            'jpg', 'jpeg' => imagejpeg($resized, null, 85),
            'gif' => imagegif($resized),
            'webp' => imagewebp($resized, null, 85),
            default => imagepng($resized),
        };

        return ob_get_clean();
    }
}
