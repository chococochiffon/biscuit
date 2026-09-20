<?php

namespace App\Support;

use App\Enums\CallContentType;
use App\Models\Article;
use App\Models\CallContent;
use App\Models\SinglePage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CallContentResolver
{
    /**
     * model_name からEloquentモデルクラスへのマッピング。
     *
     * @var array<string, class-string<Model>>
     */
    private const MODELS = [
        'article' => Article::class,
        'single_page' => SinglePage::class,
    ];

    /**
     * 複数のCallContentが参照するデータを、model_name単位でORMを使い一括取得して解決する。
     *
     * @param  iterable<CallContent>  $callContents
     * @return Collection<int, Model|EloquentCollection<int, Model>> CallContentのidをキーに、
     *                                                               記事/固定ページ表示(content_type: Article/SinglePage)はモデル1件、
     *                                                               リンク一覧表示(content_type: LinkList)はモデルのコレクションを値に持つ
     */
    public function resolveMany(iterable $callContents): Collection
    {
        return collect($callContents)
            ->groupBy('model_name')
            ->reduce(
                // flatMap/mergeはCallContentのid(整数キー)をarray_mergeで振り直してしまうため、
                // キーを保ったまま合成できるunionで結果を積み上げる。
                fn (Collection $results, Collection $group, string $modelName) => $results->union($this->resolveGroup($modelName, $group)),
                collect(),
            );
    }

    /**
     * 同一model_nameのCallContentをまとめて解決する(model_nameごとにクエリを一括発行する)。
     *
     * @return Collection<int, Model|EloquentCollection<int, Model>>
     */
    private function resolveGroup(string $modelName, Collection $callContents): Collection
    {
        $modelClass = self::MODELS[$modelName] ?? throw new InvalidArgumentException("未対応のmodel_nameです: {$modelName}");

        [$linkLists, $singles] = $callContents->partition(
            fn (CallContent $callContent) => $callContent->content_type === CallContentType::LinkList
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
