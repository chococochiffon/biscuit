<?php

namespace App\Models;

use App\Enums\BuilderComponentKind;
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
 * ページビルダーのコンポーネント。種類(kind。BuilderComponentKind)は 2 つで、どちらも公開すると使っているページすべてに反映される。
 * - グローバル: 複数のページで使う共通のパーツ(ヘッダー・CTA など)。ページの直下に「グローバルコンポーネント」のブロック(global。props の component に id)を置き、
 *   公開側にはこの公開中の内容をそのまま出す。内容はセクションの並びで、中にグローバルコンポーネントは置けない
 * - 独自: 使うたびに一部の項目を差し替えられる部品。中身のノードの exposed で差し替えられる項目を決め、ページには「独自コンポーネント」のブロック
 *   (custom。props の component に id、values に差し替えた値)を置く。内容はカラムの中と同じブロックの並びで、中にどちらのコンポーネントも置けない
 */
#[Fillable(['kind', 'name', 'description', 'schema_version', 'draft_content', 'published_content', 'published_at'])]
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
            'kind' => BuilderComponentKind::class,
            'schema_version' => 'integer',
            'draft_content' => 'array',
            'published_content' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * 何も置いていない下書きで、新しいコンポーネントを作る(保存はしない)。
     */
    public static function newEmpty(string $name, ?string $description = null, BuilderComponentKind $kind = BuilderComponentKind::Global): self
    {
        return new self([
            'kind' => $kind,
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
        return PageBuilder::query()
            ->with('singlePage')
            ->get()
            ->filter(fn (PageBuilder $builder) => $this->isUsedIn($builder->draft_content) || $this->isUsedIn($builder->published_content))
            ->values();
    }

    /**
     * このコンポーネントを使っているほかのコンポーネント(独自コンポーネントは、グローバルコンポーネントの中にも置ける)。
     *
     * @return Collection<int, self>
     */
    public function usedByComponents(): Collection
    {
        return self::query()
            ->whereKeyNot($this->id)
            ->orderBy('name')
            ->get()
            ->filter(fn (self $component) => $this->isUsedIn($component->draft_content) || $this->isUsedIn($component->published_content))
            ->values();
    }

    /**
     * 内容にこのコンポーネントのブロックを置いているか。
     *
     * @param  array<string, mixed>|null  $content
     */
    private function isUsedIn(?array $content): bool
    {
        foreach (BuilderContent::nodes($content ?? []) as $node) {
            if (($node['type'] ?? null) === $this->blockType() && ($node['props']['component'] ?? null) === $this->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * ページに置くブロックの種類(グローバルは global、独自は custom)。
     */
    public function blockType(): string
    {
        return $this->kind === BuilderComponentKind::Custom ? 'custom' : 'global';
    }

    /**
     * 独自コンポーネントの差し替えられる項目(公開中の内容のノードの exposed。キーは「ノードの ID.項目名」)。
     *
     * @return array<string, array{node: string, type: string, prop: string, label: string}>
     */
    public function exposedFields(): array
    {
        $fields = [];

        foreach (BuilderContent::nodes($this->published_content ?? []) as $node) {
            foreach ($node['exposed'] ?? [] as $prop => $label) {
                $fields[$node['id'].'.'.$prop] = ['node' => $node['id'], 'type' => $node['type'], 'prop' => $prop, 'label' => $label];
            }
        }

        return $fields;
    }
}
