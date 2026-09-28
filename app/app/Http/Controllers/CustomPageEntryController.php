<?php

namespace App\Http\Controllers;

use App\Enums\CustomFormType;
use App\Http\Controllers\Concerns\SyncsSortableRows;
use App\Http\Requests\StoreCustomPageEntryRequest;
use App\Http\Requests\UpdateCustomPageEntryRequest;
use App\Models\CustomPages\CustomForm;
use App\Models\CustomPages\CustomFormValue;
use App\Models\CustomPages\CustomPageDetail;
use App\Models\CustomPages\CustomPageEntry;
use App\Models\CustomPageType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * カスタムページの種類ごとのページ(user_make_○○ の行)の管理。
 * ベースの型(記事/固定ページ)の項目に加え、種類ごとのカスタムフォームの項目の入力値を保存する。
 */
class CustomPageEntryController extends Controller
{
    use SyncsSortableRows;

    /**
     * ページの一覧。記事型は公開開始日時の新しい順、固定ページ型は表示順で並べる。
     */
    public function index(CustomPageType $customPageType): View
    {
        $query = CustomPageEntry::queryFor($customPageType);

        $entries = ($customPageType->hasDetails()
            ? $query->orderBy('sort_order')->orderBy('id')
            : $query->orderByDesc('publication_start_datetime')->orderByDesc('id'))
            ->paginate(config('limits.admin_per_page'));

        return view('admin.custom_pages.index', compact('customPageType', 'entries'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(CustomPageType $customPageType): View
    {
        return view('admin.custom_pages.create', [
            'customPageType' => $customPageType,
            'forms' => CustomForm::queryFor($customPageType)->ordered()->get(),
        ]);
    }

    /**
     * ページを登録し、詳細(固定ページ型)とカスタムフォームの入力値をまとめて保存する。
     */
    public function store(StoreCustomPageEntryRequest $request, CustomPageType $customPageType): RedirectResponse
    {
        DB::transaction(function () use ($request, $customPageType) {
            $entry = CustomPageEntry::queryFor($customPageType)->create([
                ...$this->entryAttributes($request, $customPageType),
                ...($customPageType->hasDetails() ? ['sort_order' => (CustomPageEntry::queryFor($customPageType)->max('sort_order') ?? -1) + 1] : []),
            ]);

            $this->saveRelatedRows($request, $customPageType, $entry);
        });

        return redirect()->route('admin.custom-pages.entries.index', $customPageType)
            ->with('status', __(':labelを登録しました。', ['label' => $customPageType->label]));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CustomPageType $customPageType, int $entry): View
    {
        $entry = CustomPageEntry::queryFor($customPageType)->findOrFail($entry);

        return view('admin.custom_pages.edit', [
            'customPageType' => $customPageType,
            'entry' => $entry,
            'forms' => CustomForm::queryFor($customPageType)->ordered()->get(),
            'details' => $customPageType->hasDetails()
                ? CustomPageDetail::queryFor($customPageType)->where($customPageType->entryForeignKey(), $entry->id)->ordered()->get()
                : collect(),
            'values' => CustomFormValue::queryFor($customPageType)
                ->where($customPageType->entryForeignKey(), $entry->id)
                ->pluck('value', $customPageType->formForeignKey()),
        ]);
    }

    /**
     * ページを更新し、詳細(固定ページ型)とカスタムフォームの入力値をまとめて保存する。
     */
    public function update(UpdateCustomPageEntryRequest $request, CustomPageType $customPageType, int $entry): RedirectResponse
    {
        $entry = CustomPageEntry::queryFor($customPageType)->findOrFail($entry);

        DB::transaction(function () use ($request, $customPageType, $entry) {
            $entry->update($this->entryAttributes($request, $customPageType));

            $this->saveRelatedRows($request, $customPageType, $entry);
        });

        return redirect()->route('admin.custom-pages.entries.index', $customPageType)
            ->with('status', __(':labelを更新しました。', ['label' => $customPageType->label]));
    }

    /**
     * ページを論理削除する(詳細とカスタムフォームの入力値もあわせて論理削除する)。
     */
    public function destroy(CustomPageType $customPageType, int $entry): RedirectResponse
    {
        $entry = CustomPageEntry::queryFor($customPageType)->findOrFail($entry);

        DB::transaction(function () use ($customPageType, $entry) {
            CustomFormValue::queryFor($customPageType)->where($customPageType->entryForeignKey(), $entry->id)->delete();

            if ($customPageType->hasDetails()) {
                CustomPageDetail::queryFor($customPageType)->where($customPageType->entryForeignKey(), $entry->id)->delete();
            }

            $entry->delete();
        });

        return redirect()->route('admin.custom-pages.entries.index', $customPageType)
            ->with('status', __(':labelを削除しました。', ['label' => $customPageType->label]));
    }

    /**
     * 本体に保存する値(ベースの型ごとの項目と公開期間)。
     *
     * @return array<string, mixed>
     */
    private function entryAttributes(StoreCustomPageEntryRequest $request, CustomPageType $customPageType): array
    {
        $columns = $customPageType->hasDetails() ? ['short_sentences'] : ['content', 'approval'];

        return $request->safe()->only(['title', ...$columns, 'publication_start_datetime', 'publication_end_datetime']);
    }

    /**
     * 詳細(固定ページ型)を送信された行に同期し、カスタムフォームの入力値を保存する。
     */
    private function saveRelatedRows(StoreCustomPageEntryRequest $request, CustomPageType $customPageType, CustomPageEntry $entry): void
    {
        if ($customPageType->hasDetails()) {
            $foreignKey = $customPageType->entryForeignKey();

            $this->syncSortableRows(
                CustomPageDetail::queryFor($customPageType)->where($foreignKey, $entry->id),
                $request->validated('details', []),
                fn (array $row) => [
                    $foreignKey => $entry->id,
                    'sub_title' => $row['sub_title'],
                    'contents' => $row['contents'] ?? null,
                ],
            );
        }

        $this->saveFormValues($request, $customPageType, $entry);
    }

    /**
     * カスタムフォームの項目ごとの入力値を、ページに紐づけて保存する(未入力は null。チェックボックスは選んだ選択肢の配列)。
     */
    private function saveFormValues(StoreCustomPageEntryRequest $request, CustomPageType $customPageType, CustomPageEntry $entry): void
    {
        $inputs = $request->validated('custom_fields', []);

        foreach ($request->forms() as $form) {
            $value = $inputs[$form->id] ?? null;

            if ($form->customs_form_type === CustomFormType::Checkbox) {
                $value = array_values(array_filter((array) $value, fn ($option) => filled($option)));
            }

            CustomFormValue::queryFor($customPageType)->updateOrCreate(
                [$customPageType->formForeignKey() => $form->id, $customPageType->entryForeignKey() => $entry->id],
                ['value' => $value],
            );
        }
    }
}
