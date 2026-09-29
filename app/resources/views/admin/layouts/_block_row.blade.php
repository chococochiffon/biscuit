@php
    /**
     * @var string $index
     * @var int|string|null $id
     * @var int|null $region
     * @var int|null $blockType
     * @var string|null $title
     * @var string|null $subtitle
     * @var int|null $callType
     * @var int|null $contentModelRelationId
     * @var int|null $viewCount
     * @var string|null $content
     * @var int $sortOrder
     * @var \Illuminate\Support\Collection $navItems 表示用の行(RepeaterRows)
     * @var \Illuminate\Support\Collection $contentModelRelations
     * @var \Illuminate\Support\Collection $singlePages
     * @var \Illuminate\Support\Collection $customPageTypes
     */
    $selectedBlockType = \App\Enums\LayoutBlockType::tryFrom((int) $blockType);
    $isCallContent = $selectedBlockType === \App\Enums\LayoutBlockType::CallContent;
    $isFreeText = $selectedBlockType === \App\Enums\LayoutBlockType::FreeText;
    $isNavMenu = $selectedBlockType === \App\Enums\LayoutBlockType::NavMenu;
    $hasHeading = (bool) $selectedBlockType?->hasHeading();
@endphp

<div class="layout-block-row border rounded p-2 mb-2" data-role="layout-block-row">
    @if ($id)
        <input type="hidden" name="blocks[{{ $index }}][id]" value="{{ $id }}">
    @endif
    <input type="hidden" name="blocks[{{ $index }}][sort_order]" value="{{ $sortOrder }}" data-role="sort-order">
    <input type="hidden" name="blocks[{{ $index }}][region]" value="{{ $region }}" data-role="region-input">
    {{-- 呼び出しコンテンツの選択肢の絞り込み(call-contents.js)に使う表示箇所。送信はしない --}}
    <input type="hidden" value="{{ \App\Models\LayoutBlock::CALL_CONTENT_PLACE->value }}" data-role="place-select">

    <div class="d-flex gap-2">
        <span class="single-page-detail-handle" data-role="drag-handle" title="{{ __('ドラッグして並び替え') }}">
            <i class="bi bi-grip-vertical"></i>
        </span>

        <div class="flex-grow-1">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small">{{ __('部品の種類') }}</label>
                    <select name="blocks[{{ $index }}][block_type]" class="form-select form-select-sm form-select-auto" data-role="block-type-select" required>
                        <option value="" disabled @selected(! $selectedBlockType)>{{ __('選択してください') }}</option>
                        @foreach (\App\Enums\LayoutBlockType::cases() as $blockTypeOption)
                            <option value="{{ $blockTypeOption->value }}" @selected($selectedBlockType === $blockTypeOption)>{{ $blockTypeOption->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4" data-role="heading-fields" @if (! $hasHeading) hidden @endif>
                    <label class="form-label small">{{ __('見出し') }}</label>
                    <input
                        type="text"
                        name="blocks[{{ $index }}][title]"
                        value="{{ $title }}"
                        class="form-control form-control-sm"
                        maxlength="255"
                        placeholder="{{ __('空欄の場合は見出しを表示しません') }}"
                        @disabled(! $hasHeading)
                    >
                </div>

                <div class="col-md-3" data-role="heading-fields" @if (! $hasHeading) hidden @endif>
                    <label class="form-label small">{{ __('小見出し') }}</label>
                    <input
                        type="text"
                        name="blocks[{{ $index }}][subtitle]"
                        value="{{ $subtitle }}"
                        class="form-control form-control-sm"
                        maxlength="255"
                        @disabled(! $hasHeading)
                    >
                </div>

                <div class="col-auto ms-auto">
                    <button type="button" class="btn btn-outline-danger btn-sm" data-role="remove-row" aria-label="{{ __('削除') }}">−</button>
                </div>
            </div>

            <div class="row g-2 align-items-end mt-1" data-role="call-content-fields" @if (! $isCallContent) hidden @endif>
                <div class="col-md-3">
                    <label class="form-label small">{{ __('呼び出し方') }}</label>
                    <select name="blocks[{{ $index }}][call_type]" class="form-select form-select-sm" data-role="call-type-select" required @disabled(! $isCallContent)>
                        <option value="" disabled @selected(! $callType)>{{ __('選択してください') }}</option>
                        @foreach (\App\Enums\CallType::allowedForPlace(\App\Models\LayoutBlock::CALL_CONTENT_PLACE) as $type)
                            <option value="{{ $type->value }}" @selected($callType === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" data-role="call-type-error" hidden></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">{{ __('データ種別') }}</label>
                    <select name="blocks[{{ $index }}][content_model_relation_id]" class="form-select form-select-sm" data-role="content-model-relation-select" required @disabled(! $isCallContent)>
                        <option value="" disabled @selected(! $contentModelRelationId)>{{ __('選択してください') }}</option>
                        @foreach ($contentModelRelations as $relation)
                            <option
                                value="{{ $relation->id }}"
                                data-content-type="{{ $relation->content_type->value }}"
                                data-model-name="{{ $relation->matrixModelName() }}"
                                @selected($contentModelRelationId === $relation->id)
                            >
                                {{ $relation->content_type->label() }} / {{ $relation->model_name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" data-role="content-model-relation-error" hidden></div>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">{{ __('表示件数') }}</label>
                    <input
                        type="number"
                        name="blocks[{{ $index }}][view_count]"
                        value="{{ $viewCount ?? 1 }}"
                        min="1"
                        max="100"
                        class="form-control form-control-sm"
                        data-role="view-count-input"
                        required
                        @disabled(! $isCallContent)
                    >
                </div>
            </div>

            <div class="mt-2" data-role="nav-menu-fields" @if (! $isNavMenu) hidden @endif>
                <label class="form-label small mb-0">{{ __('ナビメニューの項目') }}</label>
                <div class="form-text mb-2">{{ __('項目を登録しない場合は、Home・固定ページ(リンクリスト表示対象)・Articles・カスタムページの一覧・Gallery・FAQ を自動で並べます。') }}</div>

                <div data-role="nav-item-rows" data-next-index="{{ $navItems->count() }}" data-max-rows="{{ config('limits.layout_nav_items') }}">
                    @foreach ($navItems as $navItem)
                        @include('admin.layouts._nav_item_row', [
                            'blockIndex' => $index,
                            'index' => $navItem->index,
                            'id' => $navItem->id,
                            'linkType' => $navItem->linkType,
                            'label' => $navItem->label,
                            'url' => $navItem->url,
                            'singlePageId' => $navItem->singlePageId,
                            'customPageTypeId' => $navItem->customPageTypeId,
                            'sortOrder' => $navItem->sortOrder,
                            'enabled' => $isNavMenu,
                        ])
                    @endforeach
                </div>

                <button type="button" class="btn btn-outline-secondary btn-sm" data-role="nav-item-add">
                    {{ __('+ 項目を追加') }}
                </button>

                <template data-role="nav-item-template">
                    @include('admin.layouts._nav_item_row', [
                        'blockIndex' => $index,
                        'index' => '__NAV_INDEX__',
                        'id' => null,
                        'linkType' => null,
                        'label' => null,
                        'url' => null,
                        'singlePageId' => null,
                        'customPageTypeId' => null,
                        'sortOrder' => 0,
                        'enabled' => true,
                    ])
                </template>
            </div>

            <div class="mt-2" data-role="free-text-fields" @if (! $isFreeText) hidden @endif>
                <label class="form-label small">{{ __('本文') }}</label>
                <div class="layout-block-editor" data-role="free-text-editor"></div>
                <input type="hidden" name="blocks[{{ $index }}][content]" value="{{ $content }}" data-role="free-text-input" @disabled(! $isFreeText)>
            </div>
        </div>
    </div>
</div>
