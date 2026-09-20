<?php

namespace App\Models;

use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

#[Fillable(['site_title', 'description', 'site_icon', 'site_image'])]
class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory, SoftDeletes;

    /**
     * サイトアイコンの保存先ディレクトリ(公開ディスク基準)。
     */
    public const SITE_ICON_DIRECTORY = 'image/site_icon';

    /**
     * サイト画像の保存先ディレクトリ(公開ディスク基準)。
     */
    public const SITE_IMAGE_DIRECTORY = 'image/site_image';

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
     * 指定ディレクトリへ、命名規則(yyyymmddhhmmss_テーブル名_id)に従って画像を保存する。
     */
    private function storeNamedImage(UploadedFile $file, string $directory): string
    {
        Storage::disk('public')->makeDirectory($directory);

        $filename = now()->format('YmdHis').'_'.$this->getTable().'_'.$this->id.'.'.$file->extension();

        return $file->storeAs($directory, $filename, 'public');
    }
}
