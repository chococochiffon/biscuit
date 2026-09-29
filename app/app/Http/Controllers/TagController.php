<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * タグ管理の画面。記事一覧のタグ管理モーダル・記事編集のタグ選択が使う JSON は TagJsonController が返す。
 */
class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $tags = Tag::query()
            ->orderBy('tag_name')
            ->paginate(config('limits.admin_per_page'));

        return view('admin.tags.index', compact('tags'));
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
        AuditLogger::createWithLog(fn () => Tag::create($request->validated()));

        return redirect()->route('admin.tags.index')->with('status', __('タグを登録しました。'));
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
        AuditLogger::updateWithLog($tag, fn () => $tag->update($request->validated()));

        return redirect()->route('admin.tags.index')->with('status', __('タグを更新しました。'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        AuditLogger::deleteWithLog($tag);

        return redirect()->route('admin.tags.index')->with('status', __('タグを削除しました。'));
    }
}
