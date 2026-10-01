<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePageBuilderTemplateRequest;
use App\Models\PageBuilderTemplate;
use App\Support\AuditLogger;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderPresenter;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Http\JsonResponse;

/**
 * ページビルダーのエディタが使うテンプレートの JSON(一覧・今のページをテンプレートとして保存・削除)。
 * テンプレートを使う(下書きに写す)のはエディタの中だけで行い、保存はいつもの下書きの保存で行う。
 */
class PageBuilderTemplateJsonController extends Controller
{
    /**
     * テンプレートの一覧(登録順)。内容は今の版にそろえて返す。
     */
    public function index(): JsonResponse
    {
        return response()->json(
            PageBuilderTemplate::query()->orderBy('id')->get()->map(fn (PageBuilderTemplate $template) => $this->toJsonPayload($template)),
        );
    }

    /**
     * 内容をテンプレートとして保存する。
     */
    public function store(StorePageBuilderTemplateRequest $request): JsonResponse
    {
        $template = AuditLogger::createWithLog(fn () => PageBuilderTemplate::create([
            'name' => $request->validated('name'),
            // ビルダーの JSON は空文字を null にしないため、空の説明はここで null にする
            'description' => $request->validated('description') ?: null,
            'schema_version' => SchemaMigrator::CURRENT_VERSION,
            'content' => $request->content(),
        ]));

        return response()->json($this->toJsonPayload($template), 201);
    }

    /**
     * テンプレートを削除(論理削除)する。テンプレートから作ったページは変わらない。
     */
    public function destroy(PageBuilderTemplate $pageBuilderTemplate): JsonResponse
    {
        AuditLogger::deleteWithLog($pageBuilderTemplate);

        return response()->json(status: 204);
    }

    /**
     * @return array{id: int, name: string, description: string|null, node_count: int, content: array<string, mixed>}
     */
    private function toJsonPayload(PageBuilderTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'node_count' => iterator_count(BuilderContent::nodes($template->content)),
            'content' => BuilderPresenter::forEditor($template->content),
        ];
    }
}
