<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $tags = Tag::query()
            ->orderBy('tag_name')
            ->paginate(20);

        return view('admin.tags.index', compact('tags'));
    }

    /**
     * タグ名の部分一致でタグを検索する(記事編集画面のタグ選択用)。
     */
    public function search(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));

        $tags = Tag::query()
            ->when($keyword !== '', fn ($query) => $query->where('tag_name', 'like', '%'.$keyword.'%'))
            ->orderBy('tag_name')
            ->limit(10)
            ->get(['id', 'tag_name']);

        return response()->json($tags);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.tags.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTagRequest $request): RedirectResponse
    {
        Tag::create($request->validated());

        return redirect()->route('admin.tags.index')->with('status', 'タグを登録しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(Tag $tag): View
    {
        return view('admin.tags.show', compact('tag'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tag $tag): View
    {
        return view('admin.tags.edit', compact('tag'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        return redirect()->route('admin.tags.index')->with('status', 'タグを更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return redirect()->route('admin.tags.index')->with('status', 'タグを削除しました。');
    }
}
