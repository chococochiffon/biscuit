<?php

namespace App\Http\Resources;

use App\Models\Article;
use App\Models\SinglePage;
use App\Models\UserDetail;
use App\Support\CallContentResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallContentResource extends JsonResource
{
    /**
     * ページ(パス解決API)の文脈で解決する場合の本文(記事/固定ページ)。
     */
    private ?Model $pageContent = null;

    /**
     * ページの本文を設定する(本文内の原文枠にはこの本文が入る)。
     * collection() がコンストラクタの第2引数に要素のキーを渡すため、コンストラクタ引数にはしない。
     */
    public function withPageContent(?Model $pageContent): static
    {
        $this->pageContent = $pageContent;

        return $this;
    }

    /**
     * Transform the resource into an array.
     * フロントエンドが表示方法を切り替えるための call_type(例: link_list)・call_name(管理用ラベル)、
     * 公開側で表示する見出し title・小見出し subtitle(未設定は null)と、
     * table_name(例: articles/single_pages/user_details)をキーにした解決済みの実データを返す。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'call_type' => $this->call_type->apiName(),
            'call_name' => $this->call_name,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            $this->contentModelRelation->table_name => $this->resolveData(),
        ];
    }

    /**
     * call_type/model_name/place(とページの本文)を元にArticle/SinglePage/UserDetailの実データを解決し、整形する。
     */
    private function resolveData(): mixed
    {
        $resolved = (new CallContentResolver)->resolve($this->resource, $this->pageContent);

        return match (true) {
            $resolved instanceof EloquentCollection => $this->collectionToResource($resolved),
            $resolved instanceof Model => $this->modelToResource($resolved),
            default => null,
        };
    }

    /**
     * @param  EloquentCollection<int, Model>  $items
     */
    private function collectionToResource(EloquentCollection $items): mixed
    {
        if ($items->isEmpty()) {
            return [];
        }

        return match (true) {
            $items->first() instanceof Article => ArticleResource::collection($items),
            $items->first() instanceof SinglePage => SinglePageResource::collection($items),
            $items->first() instanceof UserDetail => UserDetailResource::collection($items),
            default => [],
        };
    }

    private function modelToResource(Model $model): mixed
    {
        return match (true) {
            $model instanceof Article => new ArticleResource($model),
            $model instanceof SinglePage => new SinglePageResource($model),
            $model instanceof UserDetail => new UserDetailResource($model),
            default => null,
        };
    }
}
