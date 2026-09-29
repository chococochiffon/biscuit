<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContentModelRelationRequest;
use App\Http\Requests\UpdateContentModelRelationRequest;
use App\Models\ContentModelRelation;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * サイト設定画面のデータ種別紐付け管理モーダル(Ajax の登録・変更・削除)が使うデータ種別紐付けの JSON。
 * 管理画面の画面は ContentModelRelationController が受け持つ。
 */
class ContentModelRelationJsonController extends Controller
{
    /**
     * データ種別紐付けの一覧を、データ種別・モデル名の順で返す。
     */
    public function index(): JsonResponse
    {
        $contentModelRelations = ContentModelRelation::query()
            ->orderBy('content_type')
            ->orderBy('model_name')
            ->get()
            ->map(fn (ContentModelRelation $relation) => $this->toJsonPayload($relation));

        return response()->json($contentModelRelations);
    }

    /**
     * データ種別紐付けを登録する。
     */
    public function store(StoreContentModelRelationRequest $request): JsonResponse
    {
        $contentModelRelation = AuditLogger::createWithLog(fn () => ContentModelRelation::create($request->validated()));

        return response()->json($this->toJsonPayload($contentModelRelation), 201);
    }

    /**
     * データ種別紐付けを変更する。
     */
    public function update(UpdateContentModelRelationRequest $request, ContentModelRelation $contentModelRelation): JsonResponse
    {
        AuditLogger::updateWithLog($contentModelRelation, fn () => $contentModelRelation->update($request->validated()));

        return response()->json($this->toJsonPayload($contentModelRelation));
    }

    /**
     * データ種別紐付けを削除(論理削除)する。呼び出しコンテンツ・レイアウトの部品で使用中の場合は削除せず、理由を 422 で返す。
     */
    public function destroy(ContentModelRelation $contentModelRelation): JsonResponse
    {
        $inUseMessage = $contentModelRelation->inUseMessage();

        if ($inUseMessage !== null) {
            return response()->json(['message' => $inUseMessage], 422);
        }

        AuditLogger::deleteWithLog($contentModelRelation);

        return response()->json(status: 204);
    }

    /**
     * 表示用の content_type_label と、呼び出し方の組み合わせで使うモデル名(matrix_model_name。カスタムページは種類のベースの型)を含める。
     *
     * @return array<string, mixed>
     */
    private function toJsonPayload(ContentModelRelation $contentModelRelation): array
    {
        return [
            'id' => $contentModelRelation->id,
            'content_type' => $contentModelRelation->content_type->value,
            'content_type_label' => $contentModelRelation->content_type->label(),
            'model_name' => $contentModelRelation->model_name,
            'table_name' => $contentModelRelation->table_name,
            'matrix_model_name' => $contentModelRelation->matrixModelName(),
        ];
    }
}
