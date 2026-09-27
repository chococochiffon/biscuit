<?php

namespace App\Models;

use App\Enums\ArticleApprovalStatus;
use App\Models\Concerns\HasPath;
use App\Models\Concerns\HasPublicationPeriod;
use App\Models\Concerns\HasPublicImages;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;

#[Fillable(['title', 'content', 'thumbnail', 'parent_path', 'slug', 'user_id', 'approval', 'publication_start_datetime', 'publication_end_datetime'])]
#[Hidden(['unique_path'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, HasPath, HasPublicationPeriod, HasPublicImages, SoftDeletes;

    /**
     * サムネイル画像の保存先ディレクトリ(公開ディスク基準)。
     */
    public const THUMBNAIL_DIRECTORY = 'image/thumbnail';

    /**
     * サムネイル未指定の場合に使用するデフォルト画像の(publicディスク基準の)パス。
     */
    public const DEFAULT_THUMBNAIL_PATH = 'image/no_thumbnail_image.png';

    /**
     * サムネイル画像の保存サイズ(幅・高さ)。アップロード画像は比率が近い方へ中央で切り抜いて縮小する。
     * 1200×630 は OGP 向けの約1.91:1、1280×720 は16:9。
     *
     * @var list<array{int, int}>
     */
    public const THUMBNAIL_SIZES = [[1200, 630], [1280, 720]];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approval' => ArticleApprovalStatus::class,
            'publication_start_datetime' => 'datetime',
            'publication_end_datetime' => 'datetime',
        ];
    }

    /**
     * サムネイル画像の公開URLを取得する(未設定の場合はデフォルト画像)。
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(fn () => self::publicImageUrl($this->thumbnail ?: self::DEFAULT_THUMBNAIL_PATH));
    }

    /**
     * サムネイル画像を THUMBNAIL_SIZES のうち比率が近いサイズへ中央で切り抜き・縮小して保存し、公開ディスク基準の保存パスを返す。
     */
    public function storeThumbnail(UploadedFile $file): string
    {
        [$sourceWidth, $sourceHeight] = getimagesize($file->getRealPath());

        // 元画像の比率に最も近い目標サイズを選ぶ(比の対数の差で比較し、横長・縦長の差を対称に扱う)
        $size = collect(self::THUMBNAIL_SIZES)
            ->sortBy(fn (array $size) => abs(log(($sourceWidth / $sourceHeight) / ($size[0] / $size[1]))))
            ->first();

        return $this->storeNamedImage($file, self::THUMBNAIL_DIRECTORY, $size);
    }

    /**
     * 公開側に表示する記事(公開ステータスが「公開」かつ公開期間内)に絞り込む。
     * 記事一覧 API・パス解決 API・呼び出しコンテンツで共通の条件。
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('approval', ArticleApprovalStatus::Published)->withinPublicationPeriod();
    }

    /**
     * 記事を投稿したユーザーを取得する(Nullの場合は管理者が登録したこととする)。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 投稿者名を取得する(user_idがNullの場合は「管理者」)。
     */
    protected function userName(): Attribute
    {
        return Attribute::get(fn () => $this->user?->name ?? __('管理者'));
    }

    /**
     * 記事に紐づくタグを取得する。
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
