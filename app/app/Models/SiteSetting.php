<?php

namespace App\Models;

use App\Models\Concerns\HasPublicImages;
use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;

#[Fillable(['site_title', 'description', 'front_url', 'api_url', 'site_icon', 'site_image'])]
class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory, HasPublicImages, SoftDeletes;

    /**
     * サイトアイコンの保存先ディレクトリ(公開ディスク基準)。
     */
    public const SITE_ICON_DIRECTORY = 'image/site_icon';

    /**
     * サイトアイコン未設定の場合に使用するデフォルト画像の(公開ディスク基準の)パス。
     */
    public const DEFAULT_SITE_ICON_PATH = 'image/favicon-32x32.png';

    /**
     * サイト画像の保存先ディレクトリ(公開ディスク基準)。
     */
    public const SITE_IMAGE_DIRECTORY = 'image/site_image';

    /**
     * サイト画像未設定の場合に使用するデフォルト画像の(公開ディスク基準の)パス。
     */
    public const DEFAULT_SITE_IMAGE_PATH = 'image/biscuit-og-image-1200x630.png';

    /**
     * サイトアイコンを保存し、公開ディスク基準の保存パスを返す。
     */
    public function storeSiteIcon(UploadedFile $file): string
    {
        return $this->storeNamedImage($file, self::SITE_ICON_DIRECTORY);
    }

    /**
     * サイト画像を保存し、公開ディスク基準の保存パスを返す。
     */
    public function storeSiteImage(UploadedFile $file): string
    {
        return $this->storeNamedImage($file, self::SITE_IMAGE_DIRECTORY);
    }

    /**
     * サイトアイコンの公開URL(未設定の場合はデフォルト画像)。
     *
     * @return Attribute<string, never>
     */
    protected function siteIconUrl(): Attribute
    {
        return Attribute::get(fn () => self::publicImageUrl($this->site_icon ?: self::DEFAULT_SITE_ICON_PATH));
    }

    /**
     * サイト画像の公開URL(未設定の場合はデフォルト画像)。
     *
     * @return Attribute<string, never>
     */
    protected function siteImageUrl(): Attribute
    {
        return Attribute::get(fn () => self::publicImageUrl($this->site_image ?: self::DEFAULT_SITE_IMAGE_PATH));
    }
}
