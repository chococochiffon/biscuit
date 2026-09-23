<?php

namespace App\Models;

use App\Enums\ArticleApprovalStatus;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['title', 'content', 'thumbnail', 'user_id', 'approval', 'publication_start_datetime', 'publication_end_datetime'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * サムネイル未指定の場合に使用するデフォルト画像の(publicディスク基準の)パス。
     */
    public const DEFAULT_THUMBNAIL_PATH = 'image/no_thumbnail_image.png';

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
        return Attribute::get(
            fn () => Storage::disk('public')->url($this->thumbnail ?: self::DEFAULT_THUMBNAIL_PATH)
        );
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
