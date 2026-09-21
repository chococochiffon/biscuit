@php
    /**
     * @var string $index
     * @var int|string|null $id
     * @var string $subTitle
     * @var string $contents
     * @var int $sortOrder
     */
@endphp

<div class="single-page-detail-row d-flex gap-2 mb-3" data-role="detail-row">
    <div class="single-page-detail-handle" data-role="drag-handle" title="{{ __('ドラッグして並び替え') }}">
        <i class="bi bi-grip-vertical"></i>
    </div>

    <div class="flex-grow-1 border rounded p-3">
        @if ($id)
            <input type="hidden" name="details[{{ $index }}][id]" value="{{ $id }}">
        @endif
        <input type="hidden" name="details[{{ $index }}][sort_order]" value="{{ $sortOrder }}" data-role="sort-order">

        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="flex-grow-1">
                <label class="form-label small">{{ __('サブタイトル') }}</label>
                <input
                    type="text"
                    name="details[{{ $index }}][sub_title]"
                    value="{{ $subTitle }}"
                    class="form-control form-control-sm"
                    required
                >
            </div>

            <button type="button" class="btn btn-outline-danger btn-sm mt-4" data-role="remove-detail">×</button>
        </div>

        <div class="mb-0">
            <label class="form-label small">{{ __('本文') }}</label>
            <div class="single-page-detail-editor" data-role="content-editor"></div>
            <textarea name="details[{{ $index }}][contents]" data-role="content-input" hidden>{{ $contents }}</textarea>
        </div>
    </div>
</div>
