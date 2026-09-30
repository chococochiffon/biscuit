<?php

namespace App\Models;

use App\Enums\ArticleApprovalStatus;
use App\Models\Concerns\HasPublicImages;
use App\Models\Concerns\HasSortOrder;
use Database\Factories\GalleryImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * ギャラリーの画像。管理者が登録した画像(user_id が null)は公開中で登録し、マイページからユーザーが投稿した画像は
 * 記事と同じく下書き → 承認待ち → 公開(approval は ArticleApprovalStatus)の流れで、公開中のものだけ公開側に出す。
 */
#[Fillable(['user_id', 'gallery_category_id', 'image', 'name', 'comment', 'sort_order', 'approval', 'review_comment'])]
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
     * 作成直後(DB から読み直す前)も既定値を持たせる。
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'approval' => 'published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approval' => ArticleApprovalStatus::class,
        ];
    }

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
     * 公開側に出す画像(公開ステータスが「公開」)だけに絞り込む。
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('approval', ArticleApprovalStatus::Published);
    }

    /**
     * 画像を投稿したユーザーを取得する(null なら管理者の投稿)。論理削除したユーザーの画像も、投稿者を「管理者」と取り違えないよう取得する。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * 管理画面に出す投稿者名(管理者の投稿なら「管理者」)。
     *
     * @return Attribute<string, never>
     */
    protected function userName(): Attribute
    {
        return Attribute::get(fn () => $this->user?->name ?? __('管理者'));
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
