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
     * Transform the resource into an array.
     * table_name(例: articles/single_pages/user_details)をキーに、解決済みの実データのみを返す。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            $this->contentModelRelation->table_name => $this->resolveData(),
        ];
    }

    /**
     * call_type/model_name/placeを元にArticle/SinglePage/UserDetailの実データを解決し、整形する。
     */
    private function resolveData(): mixed
    {
        $resolved = (new CallContentResolver)->resolve($this->resource);

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
