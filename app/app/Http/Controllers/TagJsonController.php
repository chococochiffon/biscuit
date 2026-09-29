<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 記事一覧のタグ管理モーダル(Ajax の登録・変更・削除)と、記事編集のタグ選択(インクリメンタル検索)が使うタグの JSON。
 * タグは id と tag_name だけを返す。管理画面の画面は TagController が受け持つ。
 */
class TagJsonController extends Controller
{
    /**
     * タグの一覧を名前順で返す。
     */
    public function index(): JsonResponse
    {
        return response()->json(Tag::query()->orderBy('tag_name')->get()->map(fn (Tag $tag) => $this->toJsonPayload($tag)));
    }

    /**
     * タグ名の部分一致でタグを検索する(記事編集画面のタグ選択用)。
     */
    public function search(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));

        return response()->json(Tag::query()->suggest($keyword)->get()->map(fn (Tag $tag) => $this->toJsonPayload($tag)));
    }

    /**
     * タグを登録する。
     */
    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = AuditLogger::createWithLog(fn () => Tag::create($request->validated()));

        return response()->json($this->toJsonPayload($tag), 201);
    }

    /**
     * タグの名前を変更する。
     */
    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        AuditLogger::updateWithLog($tag, fn () => $tag->update($request->validated()));

        return response()->json($this->toJsonPayload($tag));
    }

    /**
     * タグを削除(論理削除)する。
     */
    public function destroy(Tag $tag): JsonResponse
    {
        AuditLogger::deleteWithLog($tag);

        return response()->json(status: 204);
    }

    /**
     * @return array{id: int, tag_name: string}
     */
    private function toJsonPayload(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'tag_name' => $tag->tag_name,
        ];
    }
}
