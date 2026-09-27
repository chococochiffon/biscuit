<?php

namespace App\Models;

use App\Models\Concerns\HasPath;
use App\Models\Concerns\HasPublicationPeriod;
use App\Models\Concerns\HasPublicImages;
use Database\Factories\SinglePageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;

#[Fillable(['title', 'short_sentences', 'header_image', 'parent_path', 'slug', 'top_page_view', 'link_list_view', 'sort_order', 'publication_start_datetime', 'publication_end_datetime'])]
#[Hidden(['unique_path'])]
class SinglePage extends Model
{
    /** @use HasFactory<SinglePageFactory> */
    use HasFactory, HasPath, HasPublicationPeriod, HasPublicImages, SoftDeletes;

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
        return $this->storeNamedImage($file, self::HEADER_IMAGE_DIRECTORY);
    }

    /**
     * ヘッダー画像の公開URL(未設定なら null)。
     *
     * @return Attribute<string|null, never>
     */
    protected function headerImageUrl(): Attribute
    {
        return Attribute::get(fn () => self::publicImageUrl($this->header_image));
    }

    /**
     * 公開側に表示する固定ページ(公開期間内)に絞り込む。記事の published() と揃えた名前にしている。
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->withinPublicationPeriod();
    }

    /**
     * この固定ページに紐づく詳細を取得する。
     */
    public function details(): HasMany
    {
        return $this->hasMany(SinglePageDetail::class)->orderBy('sort_order');
    }
}
