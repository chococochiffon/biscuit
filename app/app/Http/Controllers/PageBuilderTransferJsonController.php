<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\SavePageBuilderRequest;
use App\Support\AuditLogger;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderPresenter;
use App\Support\Builder\BuilderTransfer;
use App\Support\Builder\BuilderValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * ページビルダーのエディタの書き出し(Export)・読み込み(Import)の JSON(ページとグローバルコンポーネントのエディタで共通)。
 * 中身の組み立てと今のサイトへの合わせ方は Support\Builder\BuilderTransfer。読み込んだ内容は保存せずに返し、エディタが下書きを置き換える。
 */
class PageBuilderTransferJsonController extends Controller
{
    /**
     * エディタの今の内容を、画像・グローバルコンポーネント・ギャラリーの分類の名前を含むファイルの中身にして返す。
     */
    public function export(SavePageBuilderRequest $request, BuilderTransfer $transfer): JsonResponse
    {
        $title = (string) $request->string('title')->limit(100, '');
        $file = $transfer->export($request->content(), $title);

        AuditLogger::record(AuditAction::Exported, 'page_builder', label: $title, metadata: [
            'nodes' => iterator_count(BuilderContent::nodes($file['content'])),
            'images' => count((array) $file['images']),
        ]);

        return response()->json($file);
    }

    /**
     * 書き出したファイルを読み込み、今のサイトに合わせた内容(エディタの形)と知らせることを返す。
     * グローバルコンポーネントのエディタ(allows_global が false)では、コンポーネントのブロックを常に展開する。
     */
    public function import(Request $request, BuilderTransfer $transfer, BuilderValidator $validator): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.config('limits.builder_import_kilobytes')],
            'allows_global' => ['boolean'],
        ]);

        try {
            $result = $transfer->import(
                json_decode((string) file_get_contents($request->file('file')->getRealPath()), true),
                $validator,
                $request->boolean('allows_global', true),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['file' => [$exception->getMessage()]]], 422);
        }

        AuditLogger::record(AuditAction::Imported, 'page_builder', label: $request->file('file')->getClientOriginalName(), metadata: [
            'nodes' => iterator_count(BuilderContent::nodes($result['content'])),
            'images' => $result['images'],
        ]);

        return response()->json([
            'content' => BuilderPresenter::forEditor($result['content']),
            'warnings' => $result['warnings'],
            'images' => $result['images'],
        ]);
    }
}
