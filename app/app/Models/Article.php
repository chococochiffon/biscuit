<?php

namespace App\Models;

use App\Enums\ArticleApprovalStatus;
use App\Enums\ContentStatus;
use App\Models\Concerns\HasApproval;
use App\Models\Concerns\HasPath;
use App\Models\Concerns\HasPublicationPeriod;
use App\Models\Concerns\HasPublicImages;
use Carbon\CarbonInterface;
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

#[Fillable(['title', 'content', 'thumbnail', 'parent_path', 'slug', 'user_id', 'approval', 'review_comment', 'first_published_at', 'publication_start_datetime', 'publication_end_datetime'])]
#[Hidden(['unique_path'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasApproval, HasFactory, HasPath, HasPublicationPeriod, HasPublicImages, SoftDeletes;

    /**
     * サムネイル画像の保存先ディレクトリ(公開ディスク基準)。
     */
    public const THUMBNAIL_DIRECTORY = 'image/thumbnail';

    /**
     * 本文のリッチテキストエディタからアップロードした画像の保存先ディレクトリ(公開ディスク基準)。
     */
    public const CONTENT_IMAGE_DIRECTORY = 'image/content';

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
            'first_published_at' => 'datetime',
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
        return $this->storeNamedImage($file, self::THUMBNAIL_DIRECTORY, self::thumbnailSizeFor($file));
    }

    /**
     * THUMBNAIL_SIZES のうち、画像の比率に最も近い保存サイズ(記事型のカスタムページのサムネイルでも使う)。
     *
     * @return array{int, int}
     */
    public static function thumbnailSizeFor(UploadedFile $file): array
    {
        [$sourceWidth, $sourceHeight] = getimagesize($file->getRealPath());

        // 比の対数の差で比較し、横長・縦長の差を対称に扱う
        return collect(self::THUMBNAIL_SIZES)
            ->sortBy(fn (array $size) => abs(log(($sourceWidth / $sourceHeight) / ($size[0] / $size[1]))))
            ->first();
    }

    /**
     * 本文用にアップロードした画像の公開 URL の先頭(ユーザーの記事の本文で、残す画像の判定に使う)。
     */
    public static function contentImageUrlPrefix(): string
    {
        return rtrim((string) self::publicImageUrl(self::CONTENT_IMAGE_DIRECTORY), '/').'/';
    }

    /**
     * 公開側に表示する記事(公開ステータスが「公開」かつ公開期間内)に絞り込む。
     * 記事一覧 API・パス解決 API・呼び出しコンテンツで共通の条件。
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->withApproval(ArticleApprovalStatus::Published)->withinPublicationPeriod();
    }

    /**
     * 予約公開の記事(公開ステータスが「公開」で、公開開始日時が指定日時(省略時は現在)より後)に絞り込む。
     */
    #[Scope]
    protected function scheduled(Builder $query, ?CarbonInterface $at = null): void
    {
        $query->withApproval(ArticleApprovalStatus::Published)->upcoming($at);
    }

    /**
     * 公開中・予約公開(公開ステータスが「公開」で、公開終了を迎えていない)なのに、サムネイルが未設定(デフォルト画像のまま)の記事に絞り込む。
     * ダッシュボードのコンテンツチェックで使う。
     */
    #[Scope]
    protected function missingThumbnail(Builder $query): void
    {
        $query->withApproval(ArticleApprovalStatus::Published)
            ->notEnded()
            ->where(fn (Builder $query) => $query
                ->whereNull('thumbnail')
                ->orWhere('thumbnail', '')
                ->orWhere('thumbnail', self::DEFAULT_THUMBNAIL_PATH));
    }

    /**
     * 現在の状態。公開ステータスが「公開」でなければ下書き・未承認、「公開」なら公開期間から決める。
     */
    public function contentStatus(): ContentStatus
    {
        return match ($this->approval) {
            ArticleApprovalStatus::Draft => ContentStatus::Draft,
            ArticleApprovalStatus::Pending => ContentStatus::Pending,
            ArticleApprovalStatus::Published => $this->publicationPeriodStatus(),
        };
    }

    /**
     * 公開ステータスを変える(保存はしない)。管理画面の個別・一括の公開設定の変更と記事の編集で共通の処理。
     * ユーザーの記事を初めて公開するときは、公開開始日時を承認した日時にする(予約公開のため未来の日時にしてあればそのまま)。
     * 一度公開した記事を編集して再承認したときは、最初に公開した日時を残す。
     */
    public function changeApproval(ArticleApprovalStatus $approval): static
    {
        $this->approval = $approval;

        if ($approval === ArticleApprovalStatus::Published && $this->first_published_at === null) {
            $this->first_published_at = now();

            if ($this->user_id !== null && ($this->publication_start_datetime === null || $this->publication_start_datetime->isPast())) {
                $this->publication_start_datetime = $this->first_published_at;
            }
        }

        return $this;
    }

    /**
     * 記事を投稿したユーザーを取得する(Nullの場合は管理者が登録したこととする)。
     * 論理削除したユーザーの記事も、投稿者を「管理者」と取り違えないよう取得する。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * 公開側に出す投稿者(user_id が null なら管理者で投稿者ページなし)。名前はユーザー詳細の名前の表示設定に従う。
     * 記事一覧 API・パス解決 API・呼び出しコンテンツで共通。user.detail を読み込んでおく。
     *
     * @return array{id: int|null, name: string, profile_path: string|null}
     */
    public function author(): array
    {
        return $this->user === null
            ? ['id' => null, 'name' => __('管理者'), 'profile_path' => null]
            : ['id' => $this->user->id, 'name' => $this->user->authorName(), 'profile_path' => $this->user->authorProfilePath()];
    }

    /**
     * 投稿者名を取得する(管理画面用のアカウント名。user_idがNullの場合は「管理者」)。
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
