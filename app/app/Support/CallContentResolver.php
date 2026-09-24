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
 * 設置場所(place)ごとにモデルごとの許可されたcall_typeが異なり(CallType::supports()で判定)、
 * 許可されていない組み合わせが渡された場合は例外を投げる。
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
     * ページ(パス解決APIで取得した記事・固定ページ)の文脈で解決する場合は $pageContent を渡し、
     * 本文内(Inside)の原文(OriginalText)枠には、固定の取得条件ではなくそのページの本文を入れる
     * (データ種別がページの本文と異なる原文枠は null)。
     */
    public function resolve(CallContent $callContent, ?Model $pageContent = null): Model|EloquentCollection|null
    {
        $callContent->loadMissing('contentModelRelation');

        if ($pageContent !== null && $this->isPageContentSlot($callContent)) {
            return $this->appliesToPage($callContent, $pageContent) ? $pageContent : null;
        }

        return match ($callContent->contentModelRelation->model_name) {
            'Article' => $this->resolveArticle($callContent),
            'SinglePage' => $this->resolveSinglePage($callContent),
            'UserDetail' => $this->resolveUserDetail($callContent),
            default => throw new InvalidArgumentException(
                "未対応のmodel_nameです: {$callContent->contentModelRelation->model_name}"
            ),
        };
    }

    /**
     * ページの文脈で、この呼び出しコンテンツを表示するかどうか。
     * 本文内(Inside)の原文(OriginalText)枠は、データ種別がページの本文(記事/固定ページ)と一致する場合だけ表示する。
     */
    public function appliesToPage(CallContent $callContent, Model $pageContent): bool
    {
        $callContent->loadMissing('contentModelRelation');

        return ! $this->isPageContentSlot($callContent)
            || $callContent->contentModelRelation->model_name === class_basename($pageContent);
    }

    /**
     * ページの本文を入れる枠(本文内の原文)かどうか。
     */
    private function isPageContentSlot(CallContent $callContent): bool
    {
        return $callContent->place === CallContentPlace::Inside && $callContent->call_type === CallType::OriginalText;
    }

    private function resolveArticle(CallContent $callContent): Model|EloquentCollection|null
    {
        $this->assertSupported($callContent, 'Article');

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
        $this->assertSupported($callContent, 'SinglePage');

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
        $this->assertSupported($callContent, 'UserDetail');

        return match ($callContent->call_type) {
            CallType::LinkList => $this->userDetailContentSource->getLinkList($callContent->view_count),
            CallType::SkillList => $this->userDetailContentSource->getSkillList($callContent->view_count),
            default => throw $this->unsupportedCallType($callContent, 'UserDetail'),
        };
    }

    /**
     * 表示箇所(place)・モデル名・呼び出し方(call_type)の組み合わせが許可されているかを検証する。
     */
    private function assertSupported(CallContent $callContent, string $modelName): void
    {
        if (! $callContent->call_type->supports($modelName, $callContent->place)) {
            throw new InvalidArgumentException(
                "{$modelName}のplace={$callContent->place->name}ではcall_type={$callContent->call_type->name}は許可されていません"
            );
        }
    }

    private function unsupportedCallType(CallContent $callContent, string $modelName): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "{$modelName}はcall_type={$callContent->call_type->name}に対応していません"
        );
    }
}
