<?php

namespace App\Models;

use App\Models\Concerns\HasBuilderContent;
use App\Models\Concerns\StoresReadableJson;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Database\Factories\PageBuilderComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * ページビルダーのグローバルコンポーネント(ヘッダー・CTA など、複数のページで使う共通のパーツ)。
 * ページには「グローバルコンポーネント」のブロック(global。props の component に id)を置き、公開側にはこの公開中の内容を出すため、
 * コンポーネントを公開すると使っているページすべてに反映される。内容はセクションの並びで、中にグローバルコンポーネントは置けない。
 */
#[Fillable(['name', 'description', 'schema_version', 'draft_content', 'published_content', 'published_at'])]
class PageBuilderComponent extends Model
{
    /** @use HasFactory<PageBuilderComponentFactory> */
    use HasBuilderContent, HasFactory, SoftDeletes, StoresReadableJson;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'draft_content' => 'array',
            'published_content' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * 何も置いていない下書きで、新しいコンポーネントを作る(保存はしない)。
     */
    public static function newEmpty(string $name, ?string $description = null): self
    {
        return new self([
            'name' => $name,
            'description' => $description,
            'schema_version' => SchemaMigrator::CURRENT_VERSION,
            'draft_content' => BuilderContent::empty(),
        ]);
    }

    /**
     * このコンポーネントを使っているページビルダー(編集中・公開中のどちらかの内容に置いているもの)。
     *
     * @return Collection<int, PageBuilder>
     */
    public function usedBy(): Collection
    {
        $uses = function (?array $content): bool {
            foreach (BuilderContent::nodes($content ?? []) as $node) {
                if (($node['type'] ?? null) === 'global' && ($node['props']['component'] ?? null) === $this->id) {
                    return true;
                }
            }

            return false;
        };

        return PageBuilder::query()
            ->with('singlePage')
            ->get()
            ->filter(fn (PageBuilder $builder) => $uses($builder->draft_content) || $uses($builder->published_content))
            ->values();
    }
}
