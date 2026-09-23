<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContentModelRelationRequest;
use App\Http\Requests\UpdateContentModelRelationRequest;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use App\Rules\AllowedTableName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentModelRelationController extends Controller
{
    /**
     * Display a listing of the resource。
     * サイト設定画面の「データ種別紐付け管理」モーダルからはJSONで一覧を取得する。
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $contentModelRelations = ContentModelRelation::query()
                ->orderBy('content_type')
                ->orderBy('model_name')
                ->get(['id', 'content_type', 'model_name', 'table_name'])
                ->map(fn (ContentModelRelation $relation) => $this->toJsonPayload($relation));

            return response()->json($contentModelRelations);
        }

        $contentModelRelations = ContentModelRelation::query()
            ->latest('updated_at')
            ->paginate(20);

        return view('admin.content_model_relations.index', compact('contentModelRelations'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $tableNames = AllowedTableName::availableTables();

        return view('admin.content_model_relations.create', compact('tableNames'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContentModelRelationRequest $request): RedirectResponse|JsonResponse
    {
        $contentModelRelation = ContentModelRelation::create($request->validated());

        if ($request->wantsJson()) {
            return response()->json($this->toJsonPayload($contentModelRelation), 201);
        }

        return redirect()->route('admin.content-model-relations.index')->with('status', 'データ種別の紐付けを登録しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(ContentModelRelation $contentModelRelation): View
    {
        return view('admin.content_model_relations.show', compact('contentModelRelation'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ContentModelRelation $contentModelRelation): View
    {
        // 登録済みのtable_nameが取得元テーブル一覧から漏れていても選択肢から消えないようにする
        $tableNames = collect(AllowedTableName::availableTables())
            ->push($contentModelRelation->table_name)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return view('admin.content_model_relations.edit', compact('contentModelRelation', 'tableNames'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContentModelRelationRequest $request, ContentModelRelation $contentModelRelation): RedirectResponse|JsonResponse
    {
        $contentModelRelation->update($request->validated());

        if ($request->wantsJson()) {
            return response()->json($this->toJsonPayload($contentModelRelation));
        }

        return redirect()->route('admin.content-model-relations.index')->with('status', 'データ種別の紐付けを更新しました。');
    }

    /**
     * Remove the specified resource from storage(call_contentsで使用中の場合は削除しない)。
     */
    public function destroy(Request $request, ContentModelRelation $contentModelRelation): RedirectResponse|JsonResponse
    {
        $isInUseByCallContent = CallContent::query()
            ->where('content_model_relation_id', $contentModelRelation->id)
            ->exists();

        if ($isInUseByCallContent) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'このデータ種別の紐付けはcall_contentsで使用されているため削除できません。'], 422);
            }

            return redirect()->route('admin.content-model-relations.index')
                ->with('error', 'このデータ種別の紐付けはcall_contentsで使用されているため削除できません。');
        }

        $contentModelRelation->delete();

        if ($request->wantsJson()) {
            return response()->json(status: 204);
        }

        return redirect()->route('admin.content-model-relations.index')->with('status', 'データ種別の紐付けを削除しました。');
    }

    /**
     * データ種別紐付け管理モーダル(サイト設定画面)向けのJSONペイロードを整形する。
     * 表示用のcontent_type_labelを含める。
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
        ];
    }
}
