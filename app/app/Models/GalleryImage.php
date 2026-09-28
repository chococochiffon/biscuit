<?php

namespace App\Models;

use App\Models\Concerns\HasPublicImages;
use App\Models\Concerns\HasSortOrder;
use Database\Factories\GalleryImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

#[Fillable(['gallery_category_id', 'image', 'name', 'comment', 'sort_order'])]
class GalleryImage extends Model
{
    /** @use HasFactory<GalleryImageFactory> */
    use HasFactory, HasPublicImages, HasSortOrder, SoftDeletes;

    /**
     * ギャラリー画像の保存先ディレクトリ(公開ディスク基準)。
     */
    public const IMAGE_DIRECTORY = 'image/gallery';

    /**
     * 保存する画像の長辺の上限。これより大きい画像は比率を保ったまま長辺がこの値になるよう縮小する(小さい画像は拡大しない)。
     */
    public const IMAGE_MAX_SIZE = 1200;

    /**
     * 画像を比率を保ったまま長辺 IMAGE_MAX_SIZE 以内に縮小して保存し、公開ディスク基準の保存パスを返す。
     * ファイル名はランダムな文字列にする。
     */
    public static function storeImage(UploadedFile $file): string
    {
        [$width, $height] = getimagesize($file->getRealPath());
        $scale = min(1, self::IMAGE_MAX_SIZE / max($width, $height));

        return self::storeResizedImage(
            $file,
            self::IMAGE_DIRECTORY.'/'.Str::random(40),
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        );
    }

    /**
     * 画像が属する分類を取得する(未分類なら null)。
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GalleryCategory::class, 'gallery_category_id');
    }

    /**
     * 画像の公開URL。
     *
     * @return Attribute<string, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => self::publicImageUrl($this->image));
    }
}
