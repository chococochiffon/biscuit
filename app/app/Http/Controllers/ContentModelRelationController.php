<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContentModelRelationRequest;
use App\Http\Requests\UpdateContentModelRelationRequest;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContentModelRelationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
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
        return view('admin.content_model_relations.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContentModelRelationRequest $request): RedirectResponse
    {
        ContentModelRelation::create($request->validated());

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
        return view('admin.content_model_relations.edit', compact('contentModelRelation'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContentModelRelationRequest $request, ContentModelRelation $contentModelRelation): RedirectResponse
    {
        $contentModelRelation->update($request->validated());

        return redirect()->route('admin.content-model-relations.index')->with('status', 'データ種別の紐付けを更新しました。');
    }

    /**
     * Remove the specified resource from storage(call_contentsで使用中の場合は削除しない)。
     */
    public function destroy(ContentModelRelation $contentModelRelation): RedirectResponse
    {
        $isInUseByCallContent = CallContent::query()
            ->where('content_type', $contentModelRelation->content_type)
            ->where('model_name', $contentModelRelation->model_name)
            ->exists();

        if ($isInUseByCallContent) {
            return redirect()->route('admin.content-model-relations.index')
                ->with('error', 'このデータ種別の紐付けはcall_contentsで使用されているため削除できません。');
        }

        $contentModelRelation->delete();

        return redirect()->route('admin.content-model-relations.index')->with('status', 'データ種別の紐付けを削除しました。');
    }
}
