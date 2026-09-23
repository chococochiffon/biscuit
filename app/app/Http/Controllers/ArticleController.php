<?php

namespace App\Http\Controllers;

use App\Enums\ArticleApprovalStatus;
use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Models\Article;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class ArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $articles = Article::query()
            ->with('user')
            ->latest('updated_at')
            ->paginate(20);

        return view('admin.articles.index', compact('articles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.articles.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreArticleRequest $request): RedirectResponse
    {
        $article = Article::create([
            'title' => $request->validated('title'),
            'content' => $request->validated('content'),
            'thumbnail' => Article::DEFAULT_THUMBNAIL_PATH,
            'user_id' => null,
            'approval' => ArticleApprovalStatus::Published,
            'publication_start_datetime' => $request->validated('publication_start_datetime'),
            'publication_end_datetime' => $request->validated('publication_end_datetime'),
        ]);

        if ($request->hasFile('thumbnail')) {
            $article->update(['thumbnail' => $this->storeThumbnail($request->file('thumbnail'), $article)]);
        }

        $this->syncTags($article, $request->validated('tags', []));

        return redirect()->route('admin.articles.index')->with('status', '記事を登録しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(Article $article): View
    {
        $article->load('tags', 'user');

        return view('admin.articles.show', compact('article'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Article $article): View
    {
        $article->load('tags');

        return view('admin.articles.edit', compact('article'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateArticleRequest $request, Article $article): RedirectResponse
    {
        $article->fill([
            'title' => $request->validated('title'),
            'content' => $request->validated('content'),
            'approval' => $request->validated('approval'),
            'publication_start_datetime' => $request->validated('publication_start_datetime'),
            'publication_end_datetime' => $request->validated('publication_end_datetime'),
        ]);

        if ($request->hasFile('thumbnail')) {
            $article->thumbnail = $this->storeThumbnail($request->file('thumbnail'), $article);
        }

        $article->save();

        $this->syncTags($article, $request->validated('tags', []));

        return redirect()->route('admin.articles.index')->with('status', '記事を更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        return redirect()->route('admin.articles.index')->with('status', '記事を削除しました。');
    }

    /**
     * 記事一覧から公開ステータス(approval)のみを更新する。
     */
    public function updateApproval(Request $request, Article $article): RedirectResponse
    {
        $validated = $request->validate([
            'approval' => ['required', new Enum(ArticleApprovalStatus::class)],
        ]);

        $article->update(['approval' => $validated['approval']]);

        return back()->with('status', '公開設定を更新しました。');
    }

    /**
     * 記事一覧でチェックボックスにより選択した複数の記事の公開ステータス(approval)を一括更新する。
     */
    public function bulkUpdateApproval(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'article_ids' => ['required', 'array'],
            'article_ids.*' => ['integer', Rule::exists('articles', 'id')],
            'approval' => ['required', new Enum(ArticleApprovalStatus::class)],
        ]);

        Article::query()->whereIn('id', $validated['article_ids'])->update(['approval' => $validated['approval']]);

        return back()->with('status', '選択した記事の公開設定を一括更新しました。');
    }

    /**
     * 本文のリッチテキストエディタから画像をアップロードする。
     */
    public function uploadContentImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $path = $request->file('image')->store('image/content', 'public');

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    }

    /**
     * サムネイル画像を保存し、公開ディスク基準の保存パスを返す。
     */
    private function storeThumbnail(UploadedFile $file, Article $article): string
    {
        $filename = now()->format('YmdHis').'_'.$article->getTable().'_'.$article->id.'.'.$file->extension();

        return $file->storeAs('image/thumbnail', $filename, 'public');
    }

    /**
     * タグ名の配列から未登録のタグを作成しつつ、記事とのタグ関連を同期する。
     *
     * @param  array<int, string>  $tagNames
     */
    private function syncTags(Article $article, array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->map(fn ($name) => Tag::firstOrCreate(['tag_name' => $name])->id)
            ->all();

        $article->tags()->sync($tagIds);
    }
}
