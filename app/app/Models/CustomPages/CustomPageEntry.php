<?php

namespace App\Models\CustomPages;

use App\Enums\ArticleApprovalStatus;
use App\Enums\CustomPageBaseType;
use App\Models\Article;
use App\Models\Concerns\BelongsToCustomPageType;
use App\Models\Concerns\HasPublicationPeriod;
use App\Models\Concerns\HasPublicImages;
use App\Models\Concerns\HasSortOrder;
use App\Models\CustomPageType;
use App\Models\SinglePage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;

/**
 * カスタムページの本体(user_make_○○ の 1 行)。列は種類のベースの型(記事/固定ページ)で違う。
 * 公開側の URL は「/カスタム名の複数形/スラッグ」(スラッグ未入力の記事型は id)。
 */
class CustomPageEntry extends Model
{
    use BelongsToCustomPageType, HasPublicationPeriod, HasPublicImages, HasSortOrder, SoftDeletes;

    /**
     * 一括代入の制限をかけない(フォームリクエストで検証した値だけを渡す)。
     * $guarded に列を並べると、Laravel が列の一覧をモデルのクラスごとにキャッシュし、
     * テーブルが種類ごとに違うこのモデルでは別の種類の列が捨てられてしまうため。
     *
     * @var list<string>
     */
    protected $guarded = [];

    protected static function tableNameFor(CustomPageType $type): string
    {
        return $type->tableName();
    }

    /**
     * @return array<string, string>
     */
    protected static function castsFor(CustomPageType $type): array
    {
        return [
            'publication_start_datetime' => 'datetime',
            'publication_end_datetime' => 'datetime',
            ...($type->base_type === CustomPageBaseType::Article ? ['approval' => ArticleApprovalStatus::class] : []),
        ];
    }

    /**
     * 種類のページを、その種類の並び順(記事型は公開開始日時の新しい順、固定ページ型は表示順。同じなら id)で取得するクエリ。
     * 管理画面の一覧と公開側で共通の並び順。
     *
     * @return Builder<self>
     */
    public static function orderedQueryFor(CustomPageType $type): Builder
    {
        $query = self::queryFor($type);

        return $type->hasDetails() ? $query->ordered() : $query->newest();
    }

    /**
     * 公開側に出すページ(記事型は公開ステータスが「公開」かつ公開期間内、固定ページ型は公開期間内)を、
     * 種類の並び順(orderedQueryFor())で取得するクエリ。
     *
     * @return Builder<self>
     */
    public static function publishedQueryFor(CustomPageType $type): Builder
    {
        return self::orderedQueryFor($type)
            ->withinPublicationPeriod()
            ->when(! $type->hasDetails(), fn (Builder $query) => $query->where('approval', ArticleApprovalStatus::Published));
    }

    /**
     * 公開側の URL(例: /recipes/nikujaga。スラッグ未入力なら id)。
     */
    public function path(): string
    {
        return $this->customPageType()->publicPath().'/'.($this->slug ?? $this->id);
    }

    /**
     * サムネイル画像(記事型)を記事と同じサイズで保存し、公開ディスク基準の保存パスを返す。
     */
    public function storeThumbnail(UploadedFile $file): string
    {
        return $this->storeNamedImage($file, Article::THUMBNAIL_DIRECTORY, Article::thumbnailSizeFor($file));
    }

    /**
     * ヘッダー画像(固定ページ型)を固定ページと同じく保存し、公開ディスク基準の保存パスを返す。
     */
    public function storeHeaderImage(UploadedFile $file): string
    {
        return $this->storeNamedImage($file, SinglePage::HEADER_IMAGE_DIRECTORY);
    }

    /**
     * サムネイル画像の公開URL(記事型。未設定なら記事と同じデフォルト画像)。
     *
     * @return Attribute<string, never>
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(fn () => self::publicImageUrl($this->thumbnail ?: Article::DEFAULT_THUMBNAIL_PATH));
    }

    /**
     * ヘッダー画像の公開URL(固定ページ型。未設定なら null)。
     *
     * @return Attribute<string|null, never>
     */
    protected function headerImageUrl(): Attribute
    {
        return Attribute::get(fn () => self::publicImageUrl($this->header_image));
    }
}
