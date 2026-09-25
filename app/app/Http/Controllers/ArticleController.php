<?php

namespace App\Http\Controllers;

use App\Enums\ArticleApprovalStatus;
use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Models\Article;
use App\Models\Tag;
use App\Support\ImageResizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class ArticleController extends Controller
{
    /**
     * 一覧のデフォルトの並び順(更新日時の新しい順)。
     */
    private const DEFAULT_SORT = 'updated_at_desc';

    /**
     * Display a listing of the resource.
     * タイトル(部分一致)・公開開始/公開終了(日付の範囲)・ステータスで検索し、選択した並び順(デフォルトは更新日時の新しい順)で表示する。
     */
    public function index(Request $request): View
    {
        $sortOptions = $this->sortOptions();

        // 不正な検索条件でリダイレクトを繰り返さないよう、妥当な値だけを採用して残りは無視する
        $filters = Validator::make($request->query(), [
            'title' => ['nullable', 'string', 'max:255'],
            'publication_start_from' => ['nullable', 'date_format:Y-m-d'],
            'publication_start_to' => ['nullable', 'date_format:Y-m-d'],
            'publication_end_from' => ['nullable', 'date_format:Y-m-d'],
            'publication_end_to' => ['nullable', 'date_format:Y-m-d'],
            'approval' => ['nullable', new Enum(ArticleApprovalStatus::class)],
            'sort' => ['nullable', Rule::in(array_keys($sortOptions))],
        ])->valid();

        $sort = $filters['sort'] ?? self::DEFAULT_SORT;
        ['column' => $column, 'direction' => $direction] = $sortOptions[$sort];

        $articles = Article::query()
            ->with('user')
            ->when(filled($filters['title'] ?? null), fn ($query) => $query->where('title', 'like', '%'.$filters['title'].'%'))
            ->when(filled($filters['approval'] ?? null), fn ($query) => $query->where('approval', $filters['approval']))
            ->filterPublicationPeriod($filters)
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->paginate(20)
            ->withQueryString();

        $isSearching = collect($filters)->except('sort')->filter(fn ($value) => filled($value))->isNotEmpty();

        return view('admin.articles.index', compact('articles', 'filters', 'sort', 'isSearching'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        // 親パスは直近に登録した記事と同じものを初期値にする(記事は同じ階層にまとめて置くことが多いため)
        $defaultParentPath = Article::query()->latest('id')->value('parent_path');

        return view('admin.articles.create', compact('defaultParentPath'));
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
            'parent_path' => $request->validated('parent_path'),
            'slug' => $request->validated('slug'),
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
            'parent_path' => $request->validated('parent_path'),
            'slug' => $request->validated('slug'),
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
     * サムネイル画像を Article::THUMBNAIL_SIZES のうち比率が近いサイズへ切り抜き・縮小して保存し、公開ディスク基準の保存パスを返す。
     */
    private function storeThumbnail(UploadedFile $file, Article $article): string
    {
        $extension = ImageResizer::extensionFor($file);
        $path = 'image/thumbnail/'.now()->format('YmdHis').'_'.$article->getTable().'_'.$article->id.'.'.$extension;

        [$sourceWidth, $sourceHeight] = getimagesize($file->getRealPath());

        // 元画像の比率に最も近い目標サイズを選ぶ(比の対数の差で比較し、横長・縦長の差を対称に扱う)
        [$width, $height] = collect(Article::THUMBNAIL_SIZES)
            ->sortBy(fn (array $size) => abs(log(($sourceWidth / $sourceHeight) / ($size[0] / $size[1]))))
            ->first();

        Storage::disk('public')->put($path, ImageResizer::cropAndResize($file->getRealPath(), $extension, $width, $height));

        return $path;
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

    /**
     * 一覧で選択可能な並び順(キー → 並び替えるカラム・方向)。一覧の見出しクリックで「項目_asc/desc」のキーが送られる。
     *
     * @return array<string, array{column: string, direction: string}>
     */
    private function sortOptions(): array
    {
        return [
            'updated_at_desc' => ['column' => 'updated_at', 'direction' => 'desc'],
            'updated_at_asc' => ['column' => 'updated_at', 'direction' => 'asc'],
            'title_asc' => ['column' => 'title', 'direction' => 'asc'],
            'title_desc' => ['column' => 'title', 'direction' => 'desc'],
            'publication_start_desc' => ['column' => 'publication_start_datetime', 'direction' => 'desc'],
            'publication_start_asc' => ['column' => 'publication_start_datetime', 'direction' => 'asc'],
            'publication_end_desc' => ['column' => 'publication_end_datetime', 'direction' => 'desc'],
            'publication_end_asc' => ['column' => 'publication_end_datetime', 'direction' => 'asc'],
            'approval_asc' => ['column' => 'approval', 'direction' => 'asc'],
            'approval_desc' => ['column' => 'approval', 'direction' => 'desc'],
        ];
    }
}
