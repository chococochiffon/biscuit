<?php

namespace App\Models;

use App\Enums\BuilderPageType;
use App\Models\Concerns\HasBuilderContent;
use App\Models\Concerns\HasPublicImages;
use App\Models\Concerns\StoresReadableJson;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Database\Factories\PageBuilderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * ページビルダーで組み立てたページの内容。対象はトップと固定ページで、対象 1 件につき有効な行は 1 つ(unique_target)。
 * 編集中の内容(draft_content)と公開中の内容(published_content)を持ち、公開側には公開中の内容だけを出す。
 * 内容の形・検証は Support\Builder を参照。
 */
#[Fillable(['page_type', 'single_page_id', 'schema_version', 'draft_content', 'published_content', 'published_at'])]
#[Hidden(['unique_target'])]
class PageBuilder extends Model
{
    /** @use HasFactory<PageBuilderFactory> */
    use HasBuilderContent, HasFactory, HasPublicImages, SoftDeletes, StoresReadableJson;

    /**
     * ビルダーの画像の保存先ディレクトリ(公開ディスク基準)。
     */
    public const IMAGE_DIRECTORY = 'image/builder';

    /**
     * ビルダーの画像の長辺の最大サイズ(px)。これより大きい画像は比率を保って縮小する。
     */
    public const IMAGE_MAX_SIZE = 1920;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_type' => BuilderPageType::class,
            'schema_version' => 'integer',
            'draft_content' => 'array',
            'published_content' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * トップのビルダー(未作成なら null)。
     */
    public static function top(): ?self
    {
        return self::query()->where('page_type', BuilderPageType::Top)->first();
    }

    /**
     * 何も置いていない下書きで、新しいビルダーを作る(保存はしない)。
     */
    public static function newEmpty(BuilderPageType $pageType, ?SinglePage $singlePage = null): self
    {
        return new self([
            'page_type' => $pageType,
            'single_page_id' => $singlePage?->id,
            'schema_version' => SchemaMigrator::CURRENT_VERSION,
            'draft_content' => BuilderContent::empty(),
        ]);
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
     * 対象の固定ページを取得する(トップは null)。
     */
    public function singlePage(): BelongsTo
    {
        return $this->belongsTo(SinglePage::class);
    }
}
