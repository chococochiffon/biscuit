<?php

namespace App\Models;

use App\Models\Concerns\HasPublicImages;
use App\Models\Concerns\HasSortOrder;
use Database\Factories\TopSliderImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

#[Fillable(['top_image', 'url', 'sort_order'])]
class TopSliderImage extends Model
{
    /** @use HasFactory<TopSliderImageFactory> */
    use HasFactory, HasPublicImages, HasSortOrder, SoftDeletes;

    /**
     * トップスライダー画像の保存先ディレクトリ(公開ディスク基準)。
     */
    public const IMAGE_DIRECTORY = 'image/top_image';

    /**
     * 保存する画像のサイズ(16:9)。切り抜いた範囲をこのサイズへ拡大・縮小する。
     */
    public const IMAGE_WIDTH = 1920;

    public const IMAGE_HEIGHT = 1080;

    /**
     * 画像を指定範囲(未指定なら中央)で16:9に切り抜いて規定サイズで保存し、公開ディスク基準の保存パスを返す。
     * ファイル名はランダムな文字列にする。
     *
     * @param  array{x: int|float, y: int|float, width: int|float, height: int|float}|null  $crop
     */
    public static function storeImage(UploadedFile $file, ?array $crop = null): string
    {
        return self::storeResizedImage($file, self::IMAGE_DIRECTORY.'/'.Str::random(40), self::IMAGE_WIDTH, self::IMAGE_HEIGHT, $crop);
    }

    /**
     * 画像の公開URL。
     *
     * @return Attribute<string, never>
     */
    protected function topImageUrl(): Attribute
    {
        return Attribute::get(fn () => self::publicImageUrl($this->top_image));
    }
}
