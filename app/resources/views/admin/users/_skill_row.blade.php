@php
    /**
     * @var string $index
     * @var int|string|null $id
     * @var string|null $name
     * @var int|string|null $level
     * @var int $sortOrder
     */
@endphp

<div class="row g-2 align-items-end mb-2 border-bottom pb-2" data-role="repeater-row">
    @if ($id)
        <input type="hidden" name="user_detail[skills][{{ $index }}][id]" value="{{ $id }}">
    @endif
    <input type="hidden" name="user_detail[skills][{{ $index }}][sort_order]" value="{{ $sortOrder }}" data-role="sort-order">

    <div class="col-auto align-self-stretch d-flex">
        <span class="single-page-detail-handle" data-role="drag-handle" title="{{ __('ドラッグして並び替え') }}">
            <i class="bi bi-grip-vertical"></i>
        </span>
    </div>

    <div class="col">
        <label class="form-label small">{{ __('スキル名') }}</label>
        <input
            type="text"
            name="user_detail[skills][{{ $index }}][name]"
            value="{{ $name }}"
            class="form-control form-control-sm"
            maxlength="255"
            required
        >
    </div>

    <div class="col-auto">
        <label class="form-label small">{{ __('習熟度(0〜100)') }}</label>
        <input
            type="number"
            name="user_detail[skills][{{ $index }}][level]"
            value="{{ $level }}"
            min="0"
            max="{{ \App\Models\UserSkill::MAX_LEVEL }}"
            class="form-control form-control-sm"
            style="width: 7rem;"
            required
        >
    </div>

    <div class="col-auto">
        <button type="button" class="btn btn-outline-danger btn-sm" data-role="remove-row">−</button>
    </div>
</div>
