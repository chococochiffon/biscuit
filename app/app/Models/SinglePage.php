<?php

namespace App\Models;

use Database\Factories\SinglePageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

#[Fillable(['title', 'short_sentences', 'header_image', 'taxonomy', 'uri', 'top_page_view', 'link_list_view', 'sort_order', 'publication_start_datetime', 'publication_end_datetime'])]
class SinglePage extends Model
{
    /** @use HasFactory<SinglePageFactory> */
    use HasFactory, SoftDeletes;

    /**
     * ヘッダー画像の保存先ディレクトリ(公開ディスク基準)。
     */
    public const HEADER_IMAGE_DIRECTORY = 'image/header_image';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'top_page_view' => 'boolean',
            'link_list_view' => 'boolean',
            'publication_start_datetime' => 'datetime',
            'publication_end_datetime' => 'datetime',
        ];
    }

    /**
     * ヘッダー画像を保存し、公開ディスク基準の保存パスを返す。
     */
    public function storeHeaderImage(UploadedFile $file): string
    {
        Storage::disk('public')->makeDirectory(self::HEADER_IMAGE_DIRECTORY);

        $filename = now()->format('YmdHis').'_'.$this->getTable().'_'.$this->id.'.'.$file->extension();

        return $file->storeAs(self::HEADER_IMAGE_DIRECTORY, $filename, 'public');
    }

    /**
     * この固定ページに紐づく詳細を取得する。
     */
    public function details(): HasMany
    {
        return $this->hasMany(SinglePageDetail::class)->orderBy('sort_order');
    }
}
