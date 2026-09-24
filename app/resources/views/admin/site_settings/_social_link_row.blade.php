@php
    /**
     * @var string $index
     * @var int|string|null $id
     * @var int|null $service
     * @var string|null $name
     * @var string|null $url
     * @var int $sortOrder
     */
@endphp

<div class="row g-2 align-items-end mb-2 border-bottom pb-2" data-role="repeater-row">
    @if ($id)
        <input type="hidden" name="social_links[{{ $index }}][id]" value="{{ $id }}">
    @endif
    <input type="hidden" name="social_links[{{ $index }}][sort_order]" value="{{ $sortOrder }}" data-role="sort-order">

    <div class="col-auto align-self-stretch d-flex">
        <span class="single-page-detail-handle" data-role="drag-handle" title="{{ __('ドラッグして並び替え') }}">
            <i class="bi bi-grip-vertical"></i>
        </span>
    </div>

    <div class="col-auto">
        <label class="form-label small">{{ __('サービス') }}</label>
        <select name="social_links[{{ $index }}][service]" class="form-select form-select-sm form-select-auto" required>
            <option value="" disabled @selected(! $service)>{{ __('選択してください') }}</option>
            @foreach (\App\Enums\SocialService::cases() as $serviceOption)
                <option value="{{ $serviceOption->value }}" @selected($service === $serviceOption->value)>{{ $serviceOption->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label small">{{ __('表示名') }}</label>
        <input
            type="text"
            name="social_links[{{ $index }}][name]"
            value="{{ $name }}"
            class="form-control form-control-sm"
            maxlength="255"
            required
        >
    </div>

    <div class="col">
        <label class="form-label small">{{ __('URL') }}</label>
        <input
            type="url"
            name="social_links[{{ $index }}][url]"
            value="{{ $url }}"
            class="form-control form-control-sm"
            maxlength="2048"
            placeholder="https://"
            required
        >
    </div>

    <div class="col-auto">
        <button type="button" class="btn btn-outline-danger btn-sm" data-role="remove-row">−</button>
    </div>
</div>
