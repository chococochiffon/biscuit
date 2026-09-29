<?php

namespace App\Http\Controllers;

use App\Enums\CustomFormType;
use App\Http\Controllers\Concerns\SyncsSortableRows;
use App\Http\Requests\StoreCustomPageTypeRequest;
use App\Http\Requests\UpdateCustomPageTypeRequest;
use App\Models\CustomPages\CustomForm;
use App\Models\CustomPageType;
use App\Support\AuditLogger;
use App\Support\CustomPages\CustomPageSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * カスタムページの種類の管理(登録時に種類ごとのテーブルを作り、編集画面でカスタムフォームの項目を定義する)。
 */
class CustomPageTypeController extends Controller
{
    use SyncsSortableRows;

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $customPageTypes = CustomPageType::query()->ordered()->get();

        return view('admin.custom_page_types.index', compact('customPageTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.custom_page_types.create');
    }

    /**
     * 種類ごとのテーブルを作ってから、種類を登録する。
     * MySQL ではテーブルの作成が暗黙にコミットされトランザクションで囲めないため、テーブルを先に作り、
     * 種類を登録できなかった場合は作ったテーブルを削除する(テーブルの作成に失敗した場合は CustomPageSchema が削除する)。
     */
    public function store(StoreCustomPageTypeRequest $request, CustomPageSchema $schema): RedirectResponse
    {
        $customPageType = new CustomPageType([
            ...$request->validated(),
            'sort_order' => CustomPageType::nextSortOrder(CustomPageType::withTrashed()),
        ]);

        $schema->create($customPageType);

        try {
            DB::transaction(function () use ($customPageType) {
                $customPageType->save();
                AuditLogger::created($customPageType);
            });
        } catch (Throwable $exception) {
            $schema->drop($customPageType);

            throw $exception;
        }

        return redirect()->route('admin.custom-page-types.edit', $customPageType)
            ->with('status', __('カスタムページを登録しました。カスタムフォームの項目を設定してください。'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CustomPageType $customPageType): View
    {
        $forms = CustomForm::queryFor($customPageType)->ordered()->get();

        return view('admin.custom_page_types.edit', compact('customPageType', 'forms'));
    }

    /**
     * 表示名を更新し、カスタムフォームの項目定義を送信された行に同期する
     * (作成/更新/削除と並び順の扱いは SyncsSortableRows::syncSortableRows() を参照)。
     */
    public function update(UpdateCustomPageTypeRequest $request, CustomPageType $customPageType): RedirectResponse
    {
        DB::transaction(function () use ($request, $customPageType) {
            $before = AuditLogger::snapshot($customPageType);

            $customPageType->update(['label' => $request->validated('label')]);

            $forms = $this->syncSortableRows(CustomForm::queryFor($customPageType), $request->validated('forms', []), function (array $row) {
                $formType = CustomFormType::from((int) $row['customs_form_type']);

                return [
                    'parts_name' => $row['parts_name'],
                    'customs_form_type' => $formType,
                    'customs_form_options' => $formType->hasOptions() ? self::parseOptions($row['options'] ?? '') : null,
                ];
            });

            AuditLogger::updated($customPageType, $before, ['forms' => $forms->summary()]);
        });

        return redirect()->route('admin.custom-page-types.edit', $customPageType)->with('status', __('カスタムページを更新しました。'));
    }

    /**
     * 種類を論理削除する。作ったテーブルと登録済みのページは残す(物理削除しない方針のため)。
     */
    public function destroy(CustomPageType $customPageType): RedirectResponse
    {
        AuditLogger::deleteWithLog($customPageType);

        return redirect()->route('admin.custom-page-types.index')->with('status', __('カスタムページを削除しました。'));
    }

    /**
     * 1 行に 1 つ書いた選択肢を、前後の空白と空行・重複を除いた配列にする。
     *
     * @return list<string>
     */
    private static function parseOptions(string $options): array
    {
        return collect(preg_split('/\R/', $options))
            ->map(fn (string $option) => trim($option))
            ->filter(fn (string $option) => $option !== '')
            ->unique()
            ->values()
            ->all();
    }
}
