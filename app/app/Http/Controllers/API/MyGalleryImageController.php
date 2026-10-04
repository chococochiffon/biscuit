<?php

namespace App\Http\Controllers\API;

use App\Enums\ArticleApprovalStatus;
use App\Http\Controllers\Concerns\HandlesUserApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\StoreMyGalleryImageRequest;
use App\Http\Requests\API\UpdateMyGalleryImageFileRequest;
use App\Http\Requests\API\UpdateMyGalleryImageRequest;
use App\Http\Resources\MyGalleryImageResource;
use App\Models\GalleryImage;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rules\Enum;
use OpenApi\Attributes as OA;

/**
 * chococo のマイページ: ログイン中のユーザーが投稿したギャラリーの画像の管理と承認の申請。流れは記事(MyArticleController)と同じで、
 * 下書きで作り、申請(submit)・取り下げ(withdraw)で承認待ちにする/下書きに戻す。公開中の画像を変更すると承認待ちに戻る。
 * 承認を飛ばす権限(users.skip_approval)のあるユーザーは、申請するとそのまま公開になり、公開中の画像を変更しても公開中のまま。
 * 画像は AppServiceProvider の myGalleryImage のバインドで、ログイン中のユーザーの画像だけを取り出す(ほかのユーザーの画像は 404)。
 */
class MyGalleryImageController extends Controller
{
    use HandlesUserApproval;

    #[OA\Get(
        path: '/me/gallery-images',
        summary: '自分が投稿したギャラリーの画像の一覧を、更新日時の新しい順に取得する',
        security: [['bearer' => []]],
        tags: ['MyGalleryImages'],
        parameters: [
            new OA\Parameter(name: 'approval', in: 'query', required: false, description: '公開ステータスで絞り込む(draft・pending・published)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'ページ番号', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: '画像の一覧(ページネーション)'),
            new OA\Response(response: 401, description: 'ログインしていない'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'approval' => ['nullable', new Enum(ArticleApprovalStatus::class)],
        ]);

        $galleryImages = $this->user($request)->galleryImages()
            ->with('category')
            ->when(filled($validated['approval'] ?? null), fn ($query) => $query->withApproval(ArticleApprovalStatus::from($validated['approval'])))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(config('limits.api_per_page'));

        return MyGalleryImageResource::collection($galleryImages);
    }

    #[OA\Post(
        path: '/me/gallery-images',
        summary: 'ギャラリーに画像を下書きで投稿する(multipart/form-data の image・name・gallery_category_id(任意)・comment(任意))',
        security: [['bearer' => []]],
        tags: ['MyGalleryImages'],
        responses: [
            new OA\Response(response: 201, description: '投稿した画像'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 422, description: '入力値が不正'),
            new OA\Response(response: 429, description: 'アップロードの回数が多すぎる'),
        ]
    )]
    public function store(StoreMyGalleryImageRequest $request): JsonResponse
    {
        $galleryImage = AuditLogger::createWithLog(fn () => $this->user($request)->galleryImages()->create([
            ...$request->safe()->except('image'),
            'image' => GalleryImage::storeImage($request->file('image')),
            'sort_order' => GalleryImage::nextSortOrder(),
            'approval' => ArticleApprovalStatus::Draft,
        ]));

        return (new MyGalleryImageResource($galleryImage->fresh()->load('category')))->response()->setStatusCode(201);
    }

    #[OA\Get(
        path: '/me/gallery-images/{id}',
        summary: '自分が投稿したギャラリーの画像を 1 件取得する(編集画面用)',
        security: [['bearer' => []]],
        tags: ['MyGalleryImages'],
        responses: [
            new OA\Response(response: 200, description: '画像'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 404, description: '画像がない(ほかのユーザーの画像を含む)'),
        ]
    )]
    public function show(GalleryImage $myGalleryImage): MyGalleryImageResource
    {
        return new MyGalleryImageResource($myGalleryImage->load('category'));
    }

    #[OA\Put(
        path: '/me/gallery-images/{id}',
        summary: '自分が投稿したギャラリーの画像の名前・分類・コメントを更新する(公開中の画像は承認待ちに戻る。承認を飛ばす権限のあるユーザーは公開中のまま)',
        security: [['bearer' => []]],
        tags: ['MyGalleryImages'],
        responses: [
            new OA\Response(response: 200, description: '更新後の画像'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 404, description: '画像がない'),
            new OA\Response(response: 422, description: '入力値が不正'),
        ]
    )]
    public function update(UpdateMyGalleryImageRequest $request, GalleryImage $myGalleryImage): MyGalleryImageResource
    {
        AuditLogger::updateWithLog($myGalleryImage, function () use ($request, $myGalleryImage) {
            $myGalleryImage->fill($request->validated());
            $this->backToPendingIfPublished($myGalleryImage, $this->user($request));
            $myGalleryImage->save();
        });

        return new MyGalleryImageResource($myGalleryImage->fresh()->load('category'));
    }

    #[OA\Post(
        path: '/me/gallery-images/{id}/image',
        summary: '画像ファイルを変更する(multipart/form-data の image。公開中の画像は承認待ちに戻る。承認を飛ばす権限のあるユーザーは公開中のまま)',
        security: [['bearer' => []]],
        tags: ['MyGalleryImages'],
        responses: [
            new OA\Response(response: 200, description: '更新後の画像'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 404, description: '画像がない'),
            new OA\Response(response: 422, description: '画像が不正'),
            new OA\Response(response: 429, description: 'アップロードの回数が多すぎる'),
        ]
    )]
    public function updateImage(UpdateMyGalleryImageFileRequest $request, GalleryImage $myGalleryImage): MyGalleryImageResource
    {
        AuditLogger::updateWithLog($myGalleryImage, function () use ($request, $myGalleryImage) {
            $myGalleryImage->image = GalleryImage::storeImage($request->file('image'));
            $this->backToPendingIfPublished($myGalleryImage, $this->user($request));
            $myGalleryImage->save();
        });

        return new MyGalleryImageResource($myGalleryImage->fresh()->load('category'));
    }

    #[OA\Post(
        path: '/me/gallery-images/{id}/submit',
        summary: '下書きの画像の承認を申請する(承認待ちにする。承認を飛ばす権限のあるユーザーはそのまま公開する。差し戻しの理由は消える)',
        security: [['bearer' => []]],
        tags: ['MyGalleryImages'],
        responses: [
            new OA\Response(response: 200, description: '申請(公開)後の画像'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 404, description: '画像がない'),
            new OA\Response(response: 422, description: '下書きではない'),
        ]
    )]
    public function submit(Request $request, GalleryImage $myGalleryImage): MyGalleryImageResource
    {
        $this->submitForApproval($myGalleryImage, $this->user($request), __('承認を申請できるのは下書きの画像だけです。'));

        return new MyGalleryImageResource($myGalleryImage->fresh()->load('category'));
    }

    #[OA\Post(
        path: '/me/gallery-images/{id}/withdraw',
        summary: '承認の申請を取り下げる(下書きに戻す)',
        security: [['bearer' => []]],
        tags: ['MyGalleryImages'],
        responses: [
            new OA\Response(response: 200, description: '取り下げ後の画像'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 404, description: '画像がない'),
            new OA\Response(response: 422, description: '承認待ちではない'),
        ]
    )]
    public function withdraw(GalleryImage $myGalleryImage): MyGalleryImageResource
    {
        $this->withdrawSubmission($myGalleryImage, __('取り下げられるのは承認待ちの画像だけです。'));

        return new MyGalleryImageResource($myGalleryImage->fresh()->load('category'));
    }

    #[OA\Delete(
        path: '/me/gallery-images/{id}',
        summary: '自分が投稿したギャラリーの画像を削除する(公開中の画像も削除できる)',
        security: [['bearer' => []]],
        tags: ['MyGalleryImages'],
        responses: [
            new OA\Response(response: 204, description: '削除した'),
            new OA\Response(response: 401, description: 'ログインしていない'),
            new OA\Response(response: 404, description: '画像がない'),
        ]
    )]
    public function destroy(GalleryImage $myGalleryImage): JsonResponse
    {
        AuditLogger::deleteWithLog($myGalleryImage);

        return response()->json(status: 204);
    }
}
