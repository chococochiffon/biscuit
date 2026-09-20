@php
    /**
     * @var string $index
     * @var int|string|null $id
     * @var int|null $contentType
     * @var string|null $modelName
     * @var int $viewCount
     * @var int|null $place
     */
@endphp

<div class="row g-2 align-items-end mb-2 border-bottom pb-2" data-role="call-content-row">
    @if ($id)
        <input type="hidden" name="call_contents[{{ $index }}][id]" value="{{ $id }}">
    @endif

    <div class="col-md-3">
        <label class="form-label small">{{ __('コンテンツ種別') }}</label>
        <select name="call_contents[{{ $index }}][content_type]" class="form-select form-select-sm" data-role="content-type" required>
            <option value="" disabled @selected(! $contentType)>{{ __('選択してください') }}</option>
            @foreach (\App\Enums\CallContentType::cases() as $type)
                <option value="{{ $type->value }}" @selected($contentType === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label small">{{ __('モデル名') }}</label>
        <select
            name="call_contents[{{ $index }}][model_name]"
            class="form-select form-select-sm"
            data-role="model-name"
            data-initial-value="{{ $modelName }}"
            required
        >
            <option value="">{{ __('選択してください') }}</option>
        </select>
    </div>

    <div class="col-md-2">
        <label class="form-label small">{{ __('表示件数') }}</label>
        <input
            type="number"
            name="call_contents[{{ $index }}][view_count]"
            value="{{ $viewCount }}"
            min="1"
            class="form-control form-control-sm"
            required
        >
    </div>

    <div class="col-md-3">
        <label class="form-label small">{{ __('表示箇所') }}</label>
        <select name="call_contents[{{ $index }}][place]" class="form-select form-select-sm" required>
            <option value="" disabled @selected(! $place)>{{ __('選択してください') }}</option>
            @foreach (\App\Enums\CallContentPlace::cases() as $placeOption)
                <option value="{{ $placeOption->value }}" @selected($place === $placeOption->value)>{{ $placeOption->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-1">
        <button type="button" class="btn btn-outline-danger btn-sm" data-role="remove-row">−</button>
    </div>
</div>
