@php
    /**
     * @var string $blockIndex
     * @var string $index
     * @var int|string|null $id
     * @var int|null $linkType
     * @var string|null $label
     * @var string|null $url
     * @var int|null $singlePageId
     * @var int|null $customPageTypeId
     * @var int $sortOrder
     * @var bool $enabled
     * @var \Illuminate\Support\Collection $singlePages
     * @var \Illuminate\Support\Collection $customPageTypes
     */
    $name = "blocks[{$blockIndex}][nav_items][{$index}]";
    $selectedLinkType = \App\Enums\NavItemLinkType::tryFrom((int) $linkType) ?? \App\Enums\NavItemLinkType::Url;
@endphp

<div class="row g-2 align-items-end mb-2" data-role="nav-item-row">
    @if ($id)
        <input type="hidden" name="{{ $name }}[id]" value="{{ $id }}" @disabled(! $enabled)>
    @endif
    <input type="hidden" name="{{ $name }}[sort_order]" value="{{ $sortOrder }}" data-role="sort-order" @disabled(! $enabled)>

    <div class="col-auto align-self-stretch d-flex">
        @include('admin.partials._drag_handle')
    </div>

    <div class="col-auto">
        <label class="form-label small">{{ __('リンク先の種類') }}</label>
        <select name="{{ $name }}[link_type]" class="form-select form-select-sm form-select-auto" data-role="link-type-select" required @disabled(! $enabled)>
            @foreach (\App\Enums\NavItemLinkType::cases() as $linkTypeOption)
                <option value="{{ $linkTypeOption->value }}" @selected($selectedLinkType === $linkTypeOption)>{{ $linkTypeOption->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4" data-role="link-target" data-link-type="{{ \App\Enums\NavItemLinkType::Url->value }}" @if ($selectedLinkType !== \App\Enums\NavItemLinkType::Url) hidden @endif>
        <label class="form-label small">{{ __('URL') }}</label>
        <input
            type="text"
            name="{{ $name }}[url]"
            value="{{ $url }}"
            class="form-control form-control-sm"
            maxlength="2048"
            placeholder="{{ __('例: /articles、https://example.com') }}"
            required
            @disabled(! $enabled || $selectedLinkType !== \App\Enums\NavItemLinkType::Url)
        >
    </div>

    <div class="col-md-4" data-role="link-target" data-link-type="{{ \App\Enums\NavItemLinkType::SinglePage->value }}" @if ($selectedLinkType !== \App\Enums\NavItemLinkType::SinglePage) hidden @endif>
        <label class="form-label small">{{ __('固定ページ') }}</label>
        <select name="{{ $name }}[single_page_id]" class="form-select form-select-sm" required @disabled(! $enabled || $selectedLinkType !== \App\Enums\NavItemLinkType::SinglePage)>
            <option value="" disabled @selected(! $singlePageId)>{{ __('選択してください') }}</option>
            @foreach ($singlePages as $singlePage)
                <option value="{{ $singlePage->id }}" @selected($singlePageId === $singlePage->id)>{{ $singlePage->title }}（{{ $singlePage->path }}）</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4" data-role="link-target" data-link-type="{{ \App\Enums\NavItemLinkType::CustomPageType->value }}" @if ($selectedLinkType !== \App\Enums\NavItemLinkType::CustomPageType) hidden @endif>
        <label class="form-label small">{{ __('カスタムページの種類') }}</label>
        <select name="{{ $name }}[custom_page_type_id]" class="form-select form-select-sm" required @disabled(! $enabled || $selectedLinkType !== \App\Enums\NavItemLinkType::CustomPageType)>
            <option value="" disabled @selected(! $customPageTypeId)>{{ __('選択してください') }}</option>
            @foreach ($customPageTypes as $customPageType)
                <option value="{{ $customPageType->id }}" @selected($customPageTypeId === $customPageType->id)>{{ $customPageType->label }}（{{ $customPageType->publicPath() }}）</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label small">{{ __('表示名') }}</label>
        <input
            type="text"
            name="{{ $name }}[label]"
            value="{{ $label }}"
            class="form-control form-control-sm"
            maxlength="255"
            placeholder="{{ __('空欄ならページのタイトル') }}"
            @disabled(! $enabled)
        >
    </div>

    <div class="col-auto">
        <button type="button" class="btn btn-outline-danger btn-sm" data-role="remove-nav-item" aria-label="{{ __('削除') }}">−</button>
    </div>
</div>
