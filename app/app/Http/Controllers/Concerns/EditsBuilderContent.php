<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\AuditAction;
use App\Enums\BuilderContext;
use App\Models\AuditLog;
use App\Models\PageBuilderVersion;
use App\Support\AuditLogger;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderPresenter;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\CustomCss;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * ページビルダーのエディタの JSON で、編集中・公開中の内容を持つモデル(Models\Concerns\HasBuilderContent。ページの PageBuilder と
 * グローバルコンポーネントの PageBuilderComponent)の下書きの保存・公開・変更の破棄を行う共通処理。
 * エディタが知っている更新日時と今の更新日時が違えば、ほかの管理者が先に保存したとして 409 を返す(上書きしない)。
 * 公開するたびに版(PageBuilderVersion)を残し、エディタの「版の履歴」に一覧と内容を返す。
 */
trait EditsBuilderContent
{
    /**
     * 下書きの保存を監査ログに残す間隔(分)。自動保存は数秒ごとに走るため、同じ管理者・同じ対象の保存は
     * この間に 1 件だけ残す。公開・変更の破棄は毎回残す。
     */
    public const DRAFT_LOG_INTERVAL_MINUTES = 30;

    /**
     * 下書きを保存する。保存のあとに続ける処理(固定ページの更新日時を進めるなど)は $afterSave に渡す。
     *
     * @param  array<string, mixed>  $content  検証・整形済みの内容
     * @param  (callable(): mixed)|null  $afterSave
     */
    protected function saveDraft(Model $builder, array $content, string $label, ?callable $afterSave = null): void
    {
        // Custom CSS とクラス名はスーパー管理者だけが変えられる。ほかの管理者の保存では、今の下書きの値のまま保つ
        if (Gate::denies('edit-builder-css')) {
            $content = CustomCss::preserve($content, $builder->draft_content);
        }

        DB::transaction(function () use ($builder, $content, $label, $afterSave) {
            $builder->draft_content = $content;
            $builder->schema_version = $content['version'];
            $builder->save();

            if ($afterSave !== null) {
                $afterSave();
            }

            if ($this->shouldLogDraftSave($builder)) {
                AuditLogger::record(AuditAction::Updated, $builder, $label, metadata: ['nodes' => $this->countNodes($builder->draft_content)]);
            }
        });
    }

    /**
     * 下書きを検証し直してから、公開中の内容にする。検証で引っかかったら 422 の応答を返す(公開できたら null)。
     */
    protected function publishDraft(Model $builder, BuilderValidator $validator, string $label, BuilderContext $context = BuilderContext::Page): ?JsonResponse
    {
        $draft = (new SchemaMigrator)->migrate($builder->draft_content);
        $errors = $validator->errors($draft, $context);

        if ($errors !== []) {
            return response()->json([
                'message' => $errors[0]['message'],
                'errors' => collect($errors)->groupBy(fn (array $error) => $error['node'] === null ? 'content' : 'nodes.'.$error['node'])->map->pluck('message'),
            ], 422);
        }

        DB::transaction(function () use ($builder, $draft, $validator, $label) {
            $publishedNodes = $builder->isPublished() ? $this->countNodes($builder->published_content) : null;

            $builder->draft_content = $validator->normalize($draft);
            $builder->schema_version = $builder->draft_content['version'];
            $builder->publish();
            $builder->save();
            $version = $builder->recordVersion(Auth::guard('admin')->id());

            AuditLogger::record(AuditAction::Published, $builder, $label, metadata: [
                'nodes' => [$publishedNodes, $version->node_count],
                'version' => $version->id,
            ]);
        });

        return null;
    }

    /**
     * 下書きを公開中の内容に戻す。
     */
    protected function discardDraft(Model $builder, string $label): void
    {
        DB::transaction(function () use ($builder, $label) {
            $draftNodes = $this->countNodes($builder->draft_content);

            $builder->draft_content = $builder->published_content;
            $builder->save();

            AuditLogger::record(AuditAction::DraftDiscarded, $builder, $label, metadata: [
                'nodes' => [$draftNodes, $this->countNodes($builder->draft_content)],
            ]);
        });
    }

    /**
     * 版の履歴(新しい順に config('limits.builder_versions') 件。先頭が公開中の内容)。対象の行がまだなければ空。
     */
    protected function versionList(?Model $builder): JsonResponse
    {
        if ($builder === null || ! $builder->exists) {
            return response()->json([]);
        }

        $versions = $builder->latestVersions()
            ->with('administrator:id,name')
            ->limit(config('limits.builder_versions'))
            ->get(['id', 'administrator_id', 'node_count', 'created_at']);

        return response()->json($versions->values()->map(fn (PageBuilderVersion $version, int $index) => [
            'id' => $version->id,
            'published_at' => $version->created_at->toIso8601String(),
            'administrator' => $version->administrator?->name,
            'node_count' => $version->node_count,
            'current' => $index === 0 && $builder->isPublished(),
        ]));
    }

    /**
     * 版の内容(エディタの形。BuilderPresenter が今の構造の版にそろえる)。ほかの対象の版なら 404。
     */
    protected function versionContent(?Model $builder, PageBuilderVersion $version): JsonResponse
    {
        if ($builder === null || $version->versionable_type !== $builder->getMorphClass() || $version->versionable_id !== $builder->getKey()) {
            abort(404);
        }

        return response()->json([
            'id' => $version->id,
            'published_at' => $version->created_at->toIso8601String(),
            'content' => BuilderPresenter::forEditor($version->content),
        ]);
    }

    /**
     * エディタに返す内容と公開の状態。
     *
     * @return array{content: array<string, mixed>, published: bool, published_at: string|null, has_unpublished_changes: bool, updated_at: string|null}
     */
    protected function contentState(Model $builder): array
    {
        return [
            'content' => BuilderPresenter::forEditor($builder->draft_content),
            'published' => $builder->isPublished(),
            'published_at' => $builder->published_at?->toIso8601String(),
            'has_unpublished_changes' => $builder->hasUnpublishedChanges(),
            'updated_at' => $this->updatedAt($builder),
        ];
    }

    /**
     * エディタが知っている更新日時(画面を開いた・最後に保存したとき)と、今の更新日時が食い違うか(ほかの管理者が先に保存した)。
     */
    protected function conflicts(Model $builder, mixed $knownUpdatedAt): bool
    {
        return $knownUpdatedAt !== $this->updatedAt($builder);
    }

    protected function conflictResponse(Model $builder): JsonResponse
    {
        return response()->json([
            'message' => __('ほかの管理者が先に保存しました。画面を読み込み直してください。'),
            'updated_at' => $this->updatedAt($builder),
        ], 409);
    }

    protected function updatedAt(Model $builder): ?string
    {
        return $builder->exists ? $builder->updated_at?->toIso8601String() : null;
    }

    /**
     * 同じ管理者が同じ対象の下書きの保存を、DRAFT_LOG_INTERVAL_MINUTES 分以内に記録していなければ記録する。
     */
    private function shouldLogDraftSave(Model $builder): bool
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
     * @param  array<string, mixed>|null  $content
     */
    private function countNodes(?array $content): int
    {
        return iterator_count(BuilderContent::nodes($content ?? []));
    }
}
