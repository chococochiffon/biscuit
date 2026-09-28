@php
    /**
     * @var string $index
     * @var int|string|null $id
     * @var string|null $name
     * @var int $sortOrder
     */
@endphp

<div class="row g-2 align-items-center mb-2 border-bottom pb-2" data-role="repeater-row">
    @if ($id)
        <input type="hidden" name="categories[{{ $index }}][id]" value="{{ $id }}">
    @endif
    <input type="hidden" name="categories[{{ $index }}][sort_order]" value="{{ $sortOrder }}" data-role="sort-order">

    <div class="col-auto align-self-stretch d-flex">
        <span class="single-page-detail-handle" data-role="drag-handle" title="{{ __('ドラッグして並び替え') }}">
            <i class="bi bi-grip-vertical"></i>
        </span>
    </div>

    <div class="col">
        <input
            type="text"
            name="categories[{{ $index }}][name]"
            value="{{ $name }}"
            class="form-control form-control-sm"
            maxlength="128"
            aria-label="{{ __('分類名') }}"
            required
        >
    </div>

    <div class="col-auto">
        <button type="button" class="btn btn-outline-danger btn-sm" data-role="remove-row">−</button>
    </div>
</div>
