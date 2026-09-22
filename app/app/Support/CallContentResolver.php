<?php

namespace App\Support;

use App\Enums\CallContentPlace;
use App\Enums\CallType;
use App\Models\CallContent;
use App\Support\CallContent\ArticleContentSource;
use App\Support\CallContent\SinglePageContentSource;
use App\Support\CallContent\UserDetailContentSource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * 呼び出しコンテンツ(CallContent)のmodel_name/call_type/placeから、
 * 実際に表示するArticle/SinglePage/UserDetailのデータを解決する。
 * 設置場所(place)ごとに取得ルールが異なり、現時点ではTop(place=1)のみ対応している。
 */
class CallContentResolver
{
    public function __construct(
        private readonly ArticleContentSource $articleContentSource = new ArticleContentSource,
        private readonly SinglePageContentSource $singlePageContentSource = new SinglePageContentSource,
        private readonly UserDetailContentSource $userDetailContentSource = new UserDetailContentSource,
    ) {}

    /**
     * 複数のCallContentをそれぞれ解決する。
     *
     * @param  iterable<CallContent>  $callContents
     * @return Collection<int, Model|EloquentCollection<int, Model>|null> CallContentのidをキーに、
     *                                                                    単一表示は該当モデル1件(存在しなければnull)、
     *                                                                    一覧表示はモデルのコレクションを値に持つ
     */
    public function resolveMany(iterable $callContents): Collection
    {
        return EloquentCollection::make($callContents)
            ->loadMissing('contentModelRelation')
            ->mapWithKeys(fn (CallContent $callContent) => [$callContent->id => $this->resolve($callContent)]);
    }

    /**
     * 1件のCallContentを解決する。
     */
    public function resolve(CallContent $callContent): Model|EloquentCollection|null
    {
        $callContent->loadMissing('contentModelRelation');

        $this->assertPlaceIsSupported($callContent->place);

        return match ($callContent->contentModelRelation->model_name) {
            'Article' => $this->resolveArticle($callContent),
            'SinglePage' => $this->resolveSinglePage($callContent),
            'UserDetail' => $this->resolveUserDetail($callContent),
            default => throw new InvalidArgumentException(
                "未対応のmodel_nameです: {$callContent->contentModelRelation->model_name}"
            ),
        };
    }

    private function resolveArticle(CallContent $callContent): Model|EloquentCollection|null
    {
        return match ($callContent->call_type) {
            CallType::OriginalText => $this->articleContentSource->getOriginalText(),
            CallType::LinkList => $this->articleContentSource->getLinkList($callContent->view_count),
            CallType::Link => $this->articleContentSource->getLink(),
            CallType::Archive => $this->articleContentSource->getArchive($callContent->view_count),
            default => throw $this->unsupportedCallType($callContent, 'Article'),
        };
    }

    private function resolveSinglePage(CallContent $callContent): Model|EloquentCollection|null
    {
        return match ($callContent->call_type) {
            CallType::ShortSentence => $this->singlePageContentSource->getShortSentence(),
            CallType::OriginalText => $this->singlePageContentSource->getOriginalText(),
            CallType::LinkList => $this->singlePageContentSource->getLinkList(),
            CallType::Link => $this->singlePageContentSource->getLink(),
            default => throw $this->unsupportedCallType($callContent, 'SinglePage'),
        };
    }

    private function resolveUserDetail(CallContent $callContent): EloquentCollection
    {
        return match ($callContent->call_type) {
            CallType::LinkList => $this->userDetailContentSource->getLinkList($callContent->view_count),
            CallType::Archive => $this->userDetailContentSource->getArchive($callContent->view_count),
            CallType::SkillList => $this->userDetailContentSource->getSkillList($callContent->view_count),
            default => throw $this->unsupportedCallType($callContent, 'UserDetail'),
        };
    }

    /**
     * 現在対応している設置場所(Top)以外が指定された場合はエラーとする(Inside/Othersのルールは検討中)。
     */
    private function assertPlaceIsSupported(CallContentPlace $place): void
    {
        if ($place !== CallContentPlace::Top) {
            throw new InvalidArgumentException("place={$place->name} の取得ルールは未対応です(検討中)");
        }
    }

    private function unsupportedCallType(CallContent $callContent, string $modelName): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "{$modelName}はcall_type={$callContent->call_type->name}に対応していません"
        );
    }
}
