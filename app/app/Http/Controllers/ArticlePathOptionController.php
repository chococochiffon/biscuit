<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\StoreArticlePathOptionRequest;
use App\Http\Requests\UpdateArticlePathOptionRequest;
use App\Models\ArticlePathOption;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * ユーザーが記事を投稿するときに選ぶ投稿先(親パス)を、記事一覧の投稿先管理モーダルから Ajax(JSON)で管理する。
 */
class ArticlePathOptionController extends Controller
{
    /**
     * 投稿先の一覧を並び順(sort_order、同順なら id)で返す。
     */
    public function index(): JsonResponse
    {
        return response()->json(ArticlePathOption::query()->ordered()->get(['id', 'label', 'parent_path', 'sort_order']));
    }

    /**
     * 投稿先を登録する。並び順は末尾にする。
     */
    public function store(StoreArticlePathOptionRequest $request): JsonResponse
    {
        $articlePathOption = AuditLogger::createWithLog(fn () => ArticlePathOption::create([
            ...$request->validated(),
            'sort_order' => (ArticlePathOption::max('sort_order') ?? -1) + 1,
        ]));

        return response()->json($articlePathOption, 201);
    }

    /**
     * 投稿先の表示名・親パスを変更する(投稿済みの記事の URL は変わらない)。
     */
    public function update(UpdateArticlePathOptionRequest $request, ArticlePathOption $articlePathOption): JsonResponse
    {
        AuditLogger::updateWithLog($articlePathOption, fn () => $articlePathOption->update($request->validated()));

        return response()->json($articlePathOption);
    }

    /**
     * 投稿先を削除(論理削除)する(投稿済みの記事の URL は変わらない)。
     */
    public function destroy(ArticlePathOption $articlePathOption): JsonResponse
    {
        AuditLogger::deleteWithLog($articlePathOption);

        return response()->json(status: 204);
    }

    /**
     * ドラッグ&ドロップで並び替えた投稿先の並び順(sort_order)を、送信された順の連番で保存する。
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', Rule::exists('article_path_options', 'id')->withoutTrashed()],
        ]);

        // 途中で失敗しても並び順が中途半端にならないよう、まとめて保存する
        DB::transaction(function () use ($validated) {
            foreach (array_values($validated['order']) as $index => $id) {
                ArticlePathOption::query()->whereKey($id)->update(['sort_order' => $index]);
            }

            AuditLogger::record(AuditAction::Reordered, 'article_path_option', metadata: ['order' => array_map('intval', array_values($validated['order']))]);
        });

        return response()->json(status: 204);
    }
}
