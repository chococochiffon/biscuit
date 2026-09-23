@php
    /**
     * @var string $index
     * @var int|string|null $id
     * @var int|null $callType
     * @var string|null $callName
     * @var int|null $contentModelRelationId
     * @var int $viewCount
     * @var int|null $place
     * @var \Illuminate\Support\Collection $contentModelRelations
     */
@endphp

<div class="row g-2 align-items-end mb-2 border-bottom pb-2" data-role="call-content-row">
    @if ($id)
        <input type="hidden" name="call_contents[{{ $index }}][id]" value="{{ $id }}">
    @endif

    <div class="col-md-2">
        <label class="form-label small">{{ __('呼び出し名') }}</label>
        <input
            type="text"
            name="call_contents[{{ $index }}][call_name]"
            value="{{ $callName }}"
            class="form-control form-control-sm"
            required
        >
    </div>

    <div class="col-md-2">
        <label class="form-label small">{{ __('呼び出し方') }}</label>
        <select name="call_contents[{{ $index }}][call_type]" class="form-select form-select-sm" data-role="call-type-select" required>
            <option value="" disabled @selected(! $callType)>{{ __('選択してください') }}</option>
            @foreach (\App\Enums\CallType::cases() as $type)
                <option value="{{ $type->value }}" @selected($callType === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label small">{{ __('データ種別') }}</label>
        <select name="call_contents[{{ $index }}][content_model_relation_id]" class="form-select form-select-sm" data-role="content-model-relation-select" required>
            <option value="" disabled @selected(! $contentModelRelationId)>{{ __('選択してください') }}</option>
            @foreach ($contentModelRelations as $relation)
                <option
                    value="{{ $relation->id }}"
                    data-content-type="{{ $relation->content_type->value }}"
                    data-model-name="{{ $relation->model_name }}"
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
            name="call_contents[{{ $index }}][view_count]"
            value="{{ $viewCount }}"
            min="1"
            class="form-control form-control-sm"
            data-role="view-count-input"
            required
        >
    </div>

    <div class="col-md-2">
        <label class="form-label small">{{ __('表示箇所') }}</label>
        <select name="call_contents[{{ $index }}][place]" class="form-select form-select-sm" data-role="place-select" required>
            <option value="" disabled @selected(! $place)>{{ __('選択してください') }}</option>
            @foreach (\App\Enums\CallContentPlace::cases() as $placeOption)
                <option value="{{ $placeOption->value }}" @selected($place === $placeOption->value)>{{ $placeOption->label() }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-role="place-error" hidden></div>
    </div>

    <div class="col-md-1">
        <button type="button" class="btn btn-outline-danger btn-sm" data-role="remove-row">−</button>
    </div>
</div>
