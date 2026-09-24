@php
    /**
     * 一覧画面の検索フォーム用、公開開始・公開終了の日付範囲入力欄。
     *
     * @var array<string, mixed> $filters
     */
@endphp

@foreach (['publication_start' => __('公開開始'), 'publication_end' => __('公開終了')] as $prefix => $label)
    <div>
        <label for="search-{{ $prefix }}-from" class="form-label small">{{ $label }}</label>
        <div class="d-flex align-items-center gap-1">
            <input
                id="search-{{ $prefix }}-from"
                type="text"
                name="{{ $prefix }}_from"
                value="{{ $filters[$prefix.'_from'] ?? '' }}"
                class="form-control form-control-sm"
                style="width: 7.5rem;"
                data-role="date-picker"
                autocomplete="off"
                aria-label="{{ $label }}({{ __('から') }})"
            >
            <span class="small">〜</span>
            <input
                id="search-{{ $prefix }}-to"
                type="text"
                name="{{ $prefix }}_to"
                value="{{ $filters[$prefix.'_to'] ?? '' }}"
                class="form-control form-control-sm"
                style="width: 7.5rem;"
                data-role="date-picker"
                autocomplete="off"
                aria-label="{{ $label }}({{ __('まで') }})"
            >
        </div>
    </div>
@endforeach
