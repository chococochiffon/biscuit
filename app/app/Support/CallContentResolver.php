<?php

namespace App\Support;

use App\Enums\CallType;
use App\Models\Article;
use App\Models\CallContent;
use App\Models\SinglePage;
use App\Models\UserDetail;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CallContentResolver
{
    /**
     * ContentModelRelationのtable_nameからEloquentモデルクラスへのマッピング。
     *
     * @var array<string, class-string<Model>>
     */
    private const MODELS = [
        'articles' => Article::class,
        'single_pages' => SinglePage::class,
        'user_details' => UserDetail::class,
    ];

    /**
     * 複数のCallContentが参照するデータを、紐付くContentModelRelation(table_name)単位でORMを使い一括取得して解決する。
     *
     * @param  iterable<CallContent>  $callContents
     * @return Collection<int, Model|EloquentCollection<int, Model>> CallContentのidをキーに、
     *                                                               単一表示(call_type: ShortSentence/OriginalText/Link)はモデル1件、
     *                                                               リンク一覧表示(call_type: LinkList)はモデルのコレクションを値に持つ
     */
    public function resolveMany(iterable $callContents): Collection
    {
        $collection = EloquentCollection::make($callContents);
        $collection->loadMissing('contentModelRelation');

        return $collection
            ->groupBy(fn (CallContent $callContent) => $callContent->contentModelRelation->table_name)
            ->reduce(
                // flatMap/mergeはCallContentのid(整数キー)をarray_mergeで振り直してしまうため、
                // キーを保ったまま合成できるunionで結果を積み上げる。
                fn (Collection $results, Collection $group, string $tableName) => $results->union($this->resolveGroup($tableName, $group)),
                collect(),
            );
    }

    /**
     * 同一table_nameのCallContentをまとめて解決する(table_nameごとにクエリを一括発行する)。
     *
     * @return Collection<int, Model|EloquentCollection<int, Model>>
     */
    private function resolveGroup(string $tableName, Collection $callContents): Collection
    {
        $modelClass = self::MODELS[$tableName] ?? throw new InvalidArgumentException("未対応のtable_nameです: {$tableName}");

        [$linkLists, $singles] = $callContents->partition(
            fn (CallContent $callContent) => $callContent->call_type === CallType::LinkList
        );

        $results = collect();

        if ($singles->isNotEmpty()) {
            $latest = $modelClass::query()->latest()->first();

            $singles->each(fn (CallContent $callContent) => $results->put($callContent->id, $latest));
        }

        if ($linkLists->isNotEmpty()) {
            $pool = $modelClass::query()->latest()->take($linkLists->max('view_count'))->get();

            $linkLists->each(
                fn (CallContent $callContent) => $results->put($callContent->id, $pool->take($callContent->view_count)->values())
            );
        }

        return $results;
    }
}
