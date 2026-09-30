<?php

namespace App\Http\Controllers\API;

use App\Enums\ArticleApprovalStatus;
use App\Enums\AuditAction;
use App\Http\Controllers\Concerns\SavesArticle;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\StoreMyArticleRequest;
use App\Http\Requests\API\UpdateMyArticleRequest;
use App\Http\Requests\API\UpdateMyArticleThumbnailRequest;
use App\Http\Resources\ArticlePathOptionResource;
use App\Http\Resources\MyArticleResource;
use App\Http\Resources\TagResource;
use App\Models\Article;
use App\Models\ArticlePathOption;
use App\Models\Tag;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * chococo のマイページ: ログイン中のユーザーが投稿した記事の管理と承認の申請。
 * 公開ステータスは直接変えさせず、下書きで作り、申請(submit)・取り下げ(withdraw)で承認待ちにする/下書きに戻す。
 * 公開中の記事を編集すると承認待ちに戻り、管理者が承認するまで公開側に出ない。承認を飛ばす権限(users.skip_approval)のあるユーザーは、
 * 申請するとそのまま公開になり、公開中の記事を編集しても公開中のまま。
 * 記事は AppServiceProvider の myArticle のバインドで、ログイン中のユーザーの記事だけを取り出す(ほかのユーザーの記事は 404)。
 */
class MyArticleController extends Controller
{
    use SavesArticle;

    #[OA\Get(
        path: '/me/articles',
        summary: '自分の記事の一覧を、更新日時の新しい順に取得する',
        security: [['bearer' => []]],
        tags: ['MyArticles'],
        parameters: [
            new OA\Parameter(name: 'approval', in: 'query', required: false, description: '公開ステータスで絞り込む(draft・pending・published)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'ページ番号', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: '記事一覧(ページネーション)'),
            new OA\Response(response: 401, description: 'ログインしていない'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'approval' => ['nullable', new Enum(ArticleApprovalStatus::class)],
        ]);

        $articles = $this->user($request)->articles()
            ->with('tags')
            ->when(filled($validated['approval'] ?? null), fn ($query) => $query->where('approval', $validated['approval']))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(config('limits.api_per_page'));

        return MyArticleResource::collection($articles);
    }

    #[OA\Post(
        path: '/me/articles',
        summary: '記事を下書きで作成する(投稿先 article_path_option_id・タイトル・本文・スラッグ(任意)・タグ)',
        security: [['bearer' => []]],
        tags: ['MyArticles'],
        responses: [
            new OA\Response(response: 201, description: '作成した記事'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 422, description: '入力値が不正'),
        ]
    )]
    public function store(StoreMyArticleRequest $request): JsonResponse
    {
        $article = DB::transaction(function () use ($request) {
            $article = $this->user($request)->articles()->create([
                'title' => $request->validated('title'),
                'content' => $request->validated('content'),
                'thumbnail' => Article::DEFAULT_THUMBNAIL_PATH,
                'parent_path' => $request->parentPath(),
                'slug' => $request->validated('slug'),
                'approval' => ArticleApprovalStatus::Draft,
                'publication_start_datetime' => now(),
            ]);

            $this->syncTags($article, $request->validated('tags', []));

            AuditLogger::created($article, extra: $this->auditTags($article->load('tags')));

            return $article;
        });

        return (new MyArticleResource($article->fresh()->load('tags')))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/me/articles/{id}',
        summary: '自分の記事を 1 件取得する(編集画面・プレビュー用)',
        security: [['bearer' => []]],
        tags: ['MyArticles'],
        responses: [
            new OA\Response(response: 200, description: '記事'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 404, description: '記事がない(ほかのユーザーの記事を含む)'),
        ]
    )]
    public function show(Article $myArticle): MyArticleResource
    {
        return new MyArticleResource($myArticle->load('tags'));
    }

    #[OA\Put(
        path: '/me/articles/{id}',
        summary: '自分の記事を更新する(投稿先は選び直すときだけ送る。公開中の記事は承認待ちに戻る。承認を飛ばす権限のあるユーザーは公開中のまま)',
        security: [['bearer' => []]],
        tags: ['MyArticles'],
        responses: [
            new OA\Response(response: 200, description: '更新後の記事'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 404, description: '記事がない'),
            new OA\Response(response: 422, description: '入力値が不正'),
        ]
    )]
    public function update(UpdateMyArticleRequest $request, Article $myArticle): MyArticleResource
    {
        DB::transaction(function () use ($request, $myArticle) {
            $before = AuditLogger::snapshot($myArticle, $this->auditTags($myArticle));

            $myArticle->fill([
                'title' => $request->validated('title'),
                'content' => $request->validated('content'),
                'parent_path' => $request->parentPath(),
                'slug' => $request->validated('slug'),
            ]);
            $this->backToPendingIfPublished($myArticle, $this->user($request));
            $myArticle->save();

            $this->syncTags($myArticle, $request->validated('tags', []));

            AuditLogger::updated($myArticle, $before, extra: $this->auditTags($myArticle->load('tags')));
        });

        return new MyArticleResource($myArticle->fresh()->load('tags'));
    }

    #[OA\Post(
        path: '/me/articles/{id}/thumbnail',
        summary: 'サムネイル画像を変更する(multipart/form-data の thumbnail。公開中の記事は承認待ちに戻る。承認を飛ばす権限のあるユーザーは公開中のまま)',
        security: [['bearer' => []]],
        tags: ['MyArticles'],
        responses: [
            new OA\Response(response: 200, description: '更新後の記事'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 404, description: '記事がない'),
            new OA\Response(response: 422, description: '画像が不正'),
        ]
    )]
    public function updateThumbnail(UpdateMyArticleThumbnailRequest $request, Article $myArticle): MyArticleResource
    {
        DB::transaction(function () use ($request, $myArticle) {
            $before = AuditLogger::snapshot($myArticle);

            $myArticle->thumbnail = $myArticle->storeThumbnail($request->file('thumbnail'));
            $this->backToPendingIfPublished($myArticle, $this->user($request));
            $myArticle->save();

            AuditLogger::updated($myArticle, $before);
        });

        return new MyArticleResource($myArticle->fresh()->load('tags'));
    }

    #[OA\Post(
        path: '/me/articles/{id}/submit',
        summary: '下書きの記事の承認を申請する(承認待ちにする。承認を飛ばす権限のあるユーザーはそのまま公開する。差し戻しの理由は消える)',
        security: [['bearer' => []]],
        tags: ['MyArticles'],
        responses: [
            new OA\Response(response: 200, description: '申請(公開)後の記事'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 404, description: '記事がない'),
            new OA\Response(response: 422, description: '下書きではない'),
        ]
    )]
    public function submit(Request $request, Article $myArticle): MyArticleResource
    {
        $this->ensureApproval($myArticle, ArticleApprovalStatus::Draft, __('承認を申請できるのは下書きの記事だけです。'));

        // 承認を飛ばす権限のあるユーザーは、管理者が承認したときと同じくそのまま公開する(初めてなら公開開始日時も決まる)。
        // 前回の差し戻しの理由は、申請し直したら対応済みとみなして消す
        $approval = $this->user($request)->skip_approval ? ArticleApprovalStatus::Published : ArticleApprovalStatus::Pending;

        AuditLogger::updateWithLog($myArticle, function () use ($myArticle, $approval) {
            $myArticle->changeApproval($approval)->fill(['review_comment' => null])->save();
        }, action: AuditAction::StatusChanged);

        return new MyArticleResource($myArticle->fresh()->load('tags'));
    }

    #[OA\Post(
        path: '/me/articles/{id}/withdraw',
        summary: '承認の申請を取り下げる(下書きに戻す)',
        security: [['bearer' => []]],
        tags: ['MyArticles'],
        responses: [
            new OA\Response(response: 200, description: '取り下げ後の記事'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 404, description: '記事がない'),
            new OA\Response(response: 422, description: '承認待ちではない'),
        ]
    )]
    public function withdraw(Article $myArticle): MyArticleResource
    {
        $this->ensureApproval($myArticle, ArticleApprovalStatus::Pending, __('取り下げられるのは承認待ちの記事だけです。'));

        AuditLogger::updateWithLog($myArticle, fn () => $myArticle->update(['approval' => ArticleApprovalStatus::Draft]), action: AuditAction::StatusChanged);

        return new MyArticleResource($myArticle->fresh()->load('tags'));
    }

    #[OA\Delete(
        path: '/me/articles/{id}',
        summary: '自分の記事を削除する(公開中の記事も削除できる)',
        security: [['bearer' => []]],
        tags: ['MyArticles'],
        responses: [
            new OA\Response(response: 204, description: '削除した'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 404, description: '記事がない'),
        ]
    )]
    public function destroy(Article $myArticle): JsonResponse
    {
        AuditLogger::deleteWithLog($myArticle);

        return response()->json(status: 204);
    }

    #[OA\Post(
        path: '/me/articles/content-images',
        summary: '本文に入れる画像をアップロードする(multipart/form-data の image)',
        security: [['bearer' => []]],
        tags: ['MyArticles'],
        responses: [
            new OA\Response(response: 200, description: 'url(本文の img の src に使う画像の URL)'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 422, description: '画像が不正'),
            new OA\Response(response: 429, description: 'アップロードの回数が多すぎる'),
        ]
    )]
    public function uploadContentImage(Request $request): JsonResponse
    {
        return $this->storeContentImage($request);
    }

    #[OA\Get(
        path: '/me/article-paths',
        summary: '記事の投稿先(管理者が登録した親パス)の一覧を、並び順で取得する',
        security: [['bearer' => []]],
        tags: ['MyArticles'],
        responses: [
            new OA\Response(response: 200, description: '投稿先の一覧(id・label・parent_path)'),
            new OA\Response(response: 401, description: 'ログインしていない'),
        ]
    )]
    public function pathOptions(): AnonymousResourceCollection
    {
        return ArticlePathOptionResource::collection(ArticlePathOption::query()->ordered()->get());
    }

    #[OA\Get(
        path: '/me/tags',
        summary: 'タグ名で検索する(部分一致、名前順で 10 件まで。記事の編集のインクリメンタル検索用)',
        security: [['bearer' => []]],
        tags: ['MyArticles'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, description: 'キーワード', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'タグの一覧(id・name)'),
            new OA\Response(response: 401, description: 'ログインしていない'),
        ]
    )]
    public function searchTags(Request $request): AnonymousResourceCollection
    {
        return TagResource::collection(Tag::query()->suggest(trim((string) $request->query('q', '')))->get());
    }

    private function user(Request $request): User
    {
        return $request->user();
    }

    /**
     * 公開中の記事を変更したときは、管理者が承認し直すまで公開側に出さないよう承認待ちに戻す(保存はしない)。
     * 承認を飛ばす権限のあるユーザーの記事は公開中のままにする。
     */
    private function backToPendingIfPublished(Article $article, User $user): void
    {
        if ($article->approval === ArticleApprovalStatus::Published && ! $user->skip_approval) {
            $article->approval = ArticleApprovalStatus::Pending;
        }
    }

    /**
     * 今の公開ステータスが $expected でなければ 422 にする(申請・取り下げの前の確認)。
     */
    private function ensureApproval(Article $article, ArticleApprovalStatus $expected, string $message): void
    {
        if ($article->approval !== $expected) {
            throw ValidationException::withMessages(['approval' => $message]);
        }
    }
}
