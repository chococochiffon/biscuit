@php
    /**
     * 一覧の検索フォームを折りたたむ見出しボタン。検索条件が指定されているときは初期表示で展開し、「検索中」バッジを表示する。
     *
     * @var string $target 折りたたむ要素のid
     * @var bool $isSearching 検索条件が指定されているか
     */
@endphp

<button
    type="button"
    @class(['admin-search-toggle', 'collapsed' => ! $isSearching])
    data-bs-toggle="collapse"
    data-bs-target="#{{ $target }}"
    aria-expanded="{{ $isSearching ? 'true' : 'false' }}"
    aria-controls="{{ $target }}"
>
    <i class="bi bi-search"></i>
    <span>{{ __('検索条件') }}</span>
    @if ($isSearching)
        <span class="badge text-bg-primary fw-normal">{{ __('検索中') }}</span>
    @endif
    <i class="bi bi-chevron-down admin-search-toggle-icon ms-auto"></i>
</button>
