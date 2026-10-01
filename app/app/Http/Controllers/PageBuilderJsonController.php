<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\BuilderPageType;
use App\Http\Requests\SavePageBuilderRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\GalleryImageResource;
use App\Models\AuditLog;
use App\Models\GalleryCategory;
use App\Models\PageBuilder;
use App\Models\SinglePage;
use App\Models\SiteSetting;
use App\Support\AuditLogger;
use App\Support\Breadcrumbs;
use App\Support\Builder\ArticleListQuery;
use App\Support\Builder\BlockDataResolver;
use App\Support\Builder\BlockRegistry;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderPresenter;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * 管理画面のページビルダーのエディタが使う JSON(取得・下書きの保存・公開・変更の破棄・プレビューの URL・画像のアップロード・記事一覧・ナビゲーション・ギャラリーのブロックの見本)。
 * 対象はトップ(ルートに {singlePage} がない)と固定ページ。ビルダーの行は最初に下書きを保存したときに作る(取得では作らない)。
 */
class PageBuilderJsonController extends Controller
{
    /**
     * 下書きの保存を監査ログに残す間隔(分)。自動保存は数秒ごとに走るため、同じ管理者・同じビルダーの保存は
     * この間に 1 件だけ残す。公開・変更の破棄は毎回残す。
     */
    public const DRAFT_LOG_INTERVAL_MINUTES = 30;

    /**
     * プレビューの URL の有効期限(分)。
     */
    public const PREVIEW_EXPIRE_MINUTES = 30;

    /**
     * エディタを開くときの内容(ページの情報・下書き・公開の状態・ブロックの定義・画像の URL の先頭・ギャラリーの分類・パンくず)。
     */
    public function show(?SinglePage $singlePage = null): JsonResponse
    {
        return response()->json([
            ...$this->state($this->builderOrNew($singlePage), $singlePage),
            'registry' => BlockRegistry::toArray(),
            'image_base_url' => Storage::disk('public')->url(''),
            // ギャラリーのブロックの分類の選択肢
            'gallery_categories' => GalleryCategory::query()->ordered()->get(['id', 'name']),
            // パンくずのブロックの Canvas の見本(公開側と同じ組み立て。トップは空)
            'breadcrumbs' => $singlePage === null ? [] : Breadcrumbs::forPage($singlePage->path, $singlePage->title),
        ]);
    }

    /**
     * 下書きを保存する(自動保存も使う)。ほかの管理者が先に保存していたら、上書きせずに 409 を返す。
     */
    public function update(SavePageBuilderRequest $request, ?SinglePage $singlePage = null): JsonResponse
    {
        $builder = $this->builderOrNew($singlePage);

        if ($this->conflicts($builder, $request->validated('updated_at'))) {
            return $this->conflictResponse($builder);
        }

        DB::transaction(function () use ($request, $builder, $singlePage) {
            $builder->draft_content = $request->content();
            $builder->schema_version = SchemaMigrator::CURRENT_VERSION;
            $builder->save();

            // ダッシュボードの「最近編集したコンテンツ」に出るよう、固定ページの更新日時も進める
            $singlePage?->touch();

            if ($this->shouldLogDraftSave($builder)) {
                AuditLogger::record(AuditAction::Updated, $builder, $this->label($singlePage), metadata: ['nodes' => $this->countNodes($builder->draft_content)]);
            }
        });

        return response()->json($this->state($builder, $singlePage));
    }

    /**
     * 下書きを検証し直してから、公開中の内容にする。
     */
    public function publish(Request $request, BuilderValidator $validator, ?SinglePage $singlePage = null): JsonResponse
    {
        $builder = $this->existingBuilder($singlePage) ?? abort(404);

        if ($this->conflicts($builder, $request->input('updated_at'))) {
            return $this->conflictResponse($builder);
        }

        $draft = (new SchemaMigrator)->migrate($builder->draft_content);
        $errors = $validator->errors($draft);

        if ($errors !== []) {
            return response()->json([
                'message' => $errors[0]['message'],
                'errors' => collect($errors)->groupBy(fn (array $error) => $error['node'] === null ? 'content' : 'nodes.'.$error['node'])->map->pluck('message'),
            ], 422);
        }

        DB::transaction(function () use ($builder, $draft, $validator, $singlePage) {
            $publishedNodes = $builder->isPublished() ? $this->countNodes($builder->published_content) : null;

            $builder->draft_content = $validator->normalize($draft);
            $builder->schema_version = SchemaMigrator::CURRENT_VERSION;
            $builder->publish();
            $builder->save();

            AuditLogger::record(AuditAction::Published, $builder, $this->label($singlePage), metadata: [
                'nodes' => [$publishedNodes, $this->countNodes($builder->published_content)],
            ]);
        });

        return response()->json($this->state($builder, $singlePage));
    }

    /**
     * 下書きを公開中の内容に戻す(公開していなければ戻せない)。
     */
    public function discard(Request $request, ?SinglePage $singlePage = null): JsonResponse
    {
        $builder = $this->existingBuilder($singlePage) ?? abort(404);

        if (! $builder->isPublished()) {
            return response()->json(['message' => __('公開中の内容がないため、元に戻せません。')], 422);
        }

        if ($this->conflicts($builder, $request->input('updated_at'))) {
            return $this->conflictResponse($builder);
        }

        DB::transaction(function () use ($builder, $singlePage) {
            $draftNodes = $this->countNodes($builder->draft_content);

            $builder->draft_content = $builder->published_content;
            $builder->save();

            AuditLogger::record(AuditAction::DraftDiscarded, $builder, $this->label($singlePage), metadata: [
                'nodes' => [$draftNodes, $this->countNodes($builder->draft_content)],
            ]);
        });

        return response()->json($this->state($builder, $singlePage));
    }

    /**
     * 下書きを chococo で表示するプレビューの URL(期限付きの署名付き)を返す。下書きを一度も保存していなければ 404。
     */
    public function previewUrl(?SinglePage $singlePage = null): JsonResponse
    {
        $builder = $this->existingBuilder($singlePage) ?? abort(404);
        $expiresAt = Date::now()->addMinutes(self::PREVIEW_EXPIRE_MINUTES);

        // 署名はプレビュー API のパスとクエリにかかるため、chococo には id・expires・signature を渡し、chococo のサーバーが同じ API を呼ぶ
        $signedPath = URL::temporarySignedRoute('api.builder-previews.show', $expiresAt, ['pageBuilder' => $builder->id], absolute: false);
        parse_str((string) parse_url($signedPath, PHP_URL_QUERY), $query);

        return response()->json([
            'url' => SiteSetting::frontUrl().'/builder-preview?'.http_build_query(['id' => $builder->id, ...$query]),
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * 記事一覧のブロックの Canvas の見本(条件どおりの公開中の記事)。
     */
    public function articleList(Request $request): JsonResponse
    {
        $props = [
            'limit' => $request->integer('limit', 6),
            'order' => $request->query('order') === 'oldest' ? 'oldest' : 'newest',
            'parentPath' => (string) $request->query('parentPath', ''),
            'tag' => (string) $request->query('tag', ''),
        ];

        return response()->json(['articles' => ArticleResource::collection(ArticleListQuery::articles($props))->resolve()]);
    }

    /**
     * ナビゲーションのブロックの Canvas の見本(項目の出どころどおりの項目)。
     */
    public function navigation(Request $request): JsonResponse
    {
        return response()->json(['items' => BlockDataResolver::navigationItems(['source' => $request->query('source') === 'pages' ? 'pages' : 'site'])]);
    }

    /**
     * ギャラリーのブロックの Canvas の見本(条件どおりの公開中の画像)。
     */
    public function gallery(Request $request): JsonResponse
    {
        $props = [
            'category' => $request->filled('category') ? $request->integer('category') : null,
            'limit' => $request->integer('limit', 8),
        ];

        return response()->json(['images' => GalleryImageResource::collection(BlockDataResolver::galleryImages($props))->resolve()]);
    }

    /**
     * ビルダーの画像をアップロードし、保存したパス(props に入れる値)と公開 URL を返す。
     */
    public function storeImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:'.config('limits.image_max_kilobytes')],
        ]);

        $path = PageBuilder::storeImage($request->file('image'));
        AuditLogger::record(AuditAction::Uploaded, 'page_builder_image', label: $path);

        return response()->json(['path' => $path, 'url' => PageBuilder::publicImageUrl($path)], 201);
    }

    private function existingBuilder(?SinglePage $singlePage): ?PageBuilder
    {
        return $singlePage === null ? PageBuilder::top() : $singlePage->builder;
    }

    /**
     * 対象のビルダー(まだなければ、保存していない空の下書き)。
     */
    private function builderOrNew(?SinglePage $singlePage): PageBuilder
    {
        return $this->existingBuilder($singlePage)
            ?? PageBuilder::newEmpty($singlePage === null ? BuilderPageType::Top : BuilderPageType::SinglePage, $singlePage);
    }

    /**
     * エディタが知っている更新日時(画面を開いた・最後に保存したとき)と、今の更新日時が食い違うか(ほかの管理者が先に保存した)。
     */
    private function conflicts(PageBuilder $builder, mixed $knownUpdatedAt): bool
    {
        return $knownUpdatedAt !== $this->updatedAt($builder);
    }

    private function conflictResponse(PageBuilder $builder): JsonResponse
    {
        return response()->json([
            'message' => __('ほかの管理者が先に保存しました。画面を読み込み直してください。'),
            'updated_at' => $this->updatedAt($builder),
        ], 409);
    }

    /**
     * 同じ管理者が同じビルダーの下書きの保存を、DRAFT_LOG_INTERVAL_MINUTES 分以内に記録していなければ記録する。
     */
    private function shouldLogDraftSave(PageBuilder $builder): bool
    {
        return ! AuditLog::query()
            ->where('subject_type', AuditLogger::subjectType($builder))
            ->where('subject_id', $builder->id)
            ->where('action', AuditAction::Updated)
            ->where('actor_type', 'administrator')
            ->where('actor_id', Auth::guard('admin')->id())
            ->where('created_at', '>=', Date::now()->subMinutes(self::DRAFT_LOG_INTERVAL_MINUTES))
            ->exists();
    }

    /**
     * エディタに返すビルダーの状態。
     *
     * @return array<string, mixed>
     */
    private function state(PageBuilder $builder, ?SinglePage $singlePage): array
    {
        return [
            'page' => [
                'type' => $builder->page_type->value,
                'id' => $singlePage?->id,
                'title' => $this->label($singlePage),
                'path' => $singlePage?->path ?? '/',
                'use_builder' => $singlePage === null ? (bool) SiteSetting::current()?->top_use_builder : $singlePage->use_builder,
            ],
            'content' => BuilderPresenter::forEditor($builder->draft_content),
            'published' => $builder->isPublished(),
            'published_at' => $builder->published_at?->toIso8601String(),
            'has_unpublished_changes' => $builder->hasUnpublishedChanges(),
            'updated_at' => $this->updatedAt($builder),
        ];
    }

    private function updatedAt(PageBuilder $builder): ?string
    {
        return $builder->exists ? $builder->updated_at?->toIso8601String() : null;
    }

    /**
     * 監査ログ・エディタに出す対象の名前(トップ、または固定ページのタイトル)。
     */
    private function label(?SinglePage $singlePage): string
    {
        return $singlePage?->title ?? BuilderPageType::Top->label();
    }

    /**
     * @param  array<string, mixed>|null  $content
     */
    private function countNodes(?array $content): int
    {
        return iterator_count(BuilderContent::nodes($content ?? []));
    }
}
