@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mb-3">
    <label for="title" class="form-label">{{ __('タイトル') }}</label>
    <input
        id="title"
        type="text"
        name="title"
        value="{{ old('title', $singlePage->title ?? '') }}"
        required
        class="form-control"
    >
</div>

<div class="mb-3">
    <label for="short_sentences" class="form-label">{{ __('概要') }}</label>
    <input
        id="short_sentences"
        type="text"
        name="short_sentences"
        value="{{ old('short_sentences', $singlePage->short_sentences ?? '') }}"
        required
        maxlength="255"
        class="form-control"
    >
</div>

<div class="mb-3">
    <label for="taxonomy" class="form-label">{{ __('タクソノミー') }}</label>
    <input
        id="taxonomy"
        type="text"
        name="taxonomy"
        value="{{ old('taxonomy', $singlePage->taxonomy ?? '') }}"
        class="form-control"
    >
</div>

<div class="mb-3">
    <label for="uri" class="form-label">{{ __('URI') }}</label>
    <input
        id="uri"
        type="text"
        name="uri"
        value="{{ old('uri', $singlePage->uri ?? '') }}"
        class="form-control"
    >
</div>

@php
    $topPageView = (int) old('top_page_view', (int) ($singlePage->top_page_view ?? false));
    $linkListView = (int) old('link_list_view', (int) ($singlePage->link_list_view ?? false));
@endphp

<div class="mb-3">
    <label for="top_page_view" class="form-label">{{ __('Topページへ表示する') }}</label>
    <select id="top_page_view" name="top_page_view" class="form-select">
        <option value="0" @selected($topPageView === 0)>{{ __('表示しない') }}</option>
        <option value="1" @selected($topPageView === 1)>{{ __('表示する') }}</option>
    </select>
</div>

<div class="mb-3">
    <label for="link_list_view" class="form-label">{{ __('リンクリストへ表示する') }}</label>
    <select id="link_list_view" name="link_list_view" class="form-select">
        <option value="0" @selected($linkListView === 0)>{{ __('表示しない') }}</option>
        <option value="1" @selected($linkListView === 1)>{{ __('表示する') }}</option>
    </select>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="publication_start_datetime" class="form-label">{{ __('公開開始日時') }}</label>
        <input
            id="publication_start_datetime"
            type="text"
            name="publication_start_datetime"
            value="{{ old('publication_start_datetime', optional($singlePage->publication_start_datetime ?? now())->format('Y-m-d H:i')) }}"
            required
            class="form-control"
            data-role="datetime-picker"
            autocomplete="off"
        >
    </div>

    <div class="col-md-6 mb-3">
        <label for="publication_end_datetime" class="form-label">{{ __('公開終了日時') }}</label>
        <input
            id="publication_end_datetime"
            type="text"
            name="publication_end_datetime"
            value="{{ old('publication_end_datetime', optional($singlePage->publication_end_datetime ?? null)->format('Y-m-d H:i')) }}"
            class="form-control"
            data-role="datetime-picker"
            autocomplete="off"
        >
        <div class="form-text">{{ __('未指定の場合は終了日時を設定しません。') }}</div>
    </div>
</div>

@php
    $existingHeaderImageUrl = isset($singlePage) && $singlePage->header_image
        ? Illuminate\Support\Facades\Storage::disk('public')->url($singlePage->header_image)
        : null;
@endphp

<div class="mb-3">
    <label class="form-label">{{ __('ヘッダー画像') }}</label>
    <div class="image-dropzone" data-role="image-dropzone" tabindex="0" role="button" aria-label="{{ __('ヘッダー画像を選択') }}">
        <input id="header_image" type="file" name="header_image" accept="image/*" class="d-none" data-role="image-dropzone-input">

        <div class="image-dropzone-preview" data-role="image-dropzone-preview" @if (! $existingHeaderImageUrl) style="display: none;" @endif>
            <img src="{{ $existingHeaderImageUrl }}" alt="{{ __('ヘッダー画像') }}" data-role="image-dropzone-image">
            <button type="button" class="btn btn-sm btn-outline-secondary image-dropzone-remove" data-role="image-dropzone-remove" aria-label="{{ __('選択を解除') }}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="image-dropzone-placeholder" data-role="image-dropzone-placeholder" @if ($existingHeaderImageUrl) style="display: none;" @endif>
            <i class="bi bi-cloud-arrow-up"></i>
            <span class="small">{{ __('クリックまたはドラッグ&ドロップ') }}</span>
        </div>
    </div>
</div>

<hr class="my-4">

@php
    $oldDetails = old('details');

    $detailRows = $oldDetails !== null
        ? collect($oldDetails)->values()->map(fn ($row, $i) => (object) [
            'index' => (string) $i,
            'id' => $row['id'] ?? null,
            'subTitle' => $row['sub_title'] ?? '',
            'contents' => $row['contents'] ?? '',
            'sortOrder' => $row['sort_order'] ?? $i,
        ])
        : ($singlePage->details ?? collect())->values()->map(fn ($detail, $i) => (object) [
            'index' => (string) $i,
            'id' => $detail->id,
            'subTitle' => $detail->sub_title,
            'contents' => $detail->contents,
            'sortOrder' => $detail->sort_order,
        ]);
@endphp

<div class="mb-3">
    <label class="form-label">{{ __('詳細') }}</label>

    <div
        id="single-page-detail-rows"
        data-next-index="{{ $detailRows->count() }}"
    >
        @foreach ($detailRows as $row)
            @include('admin.single_pages._detail_row', [
                'index' => $row->index,
                'id' => $row->id,
                'subTitle' => $row->subTitle,
                'contents' => $row->contents,
                'sortOrder' => $row->sortOrder,
            ])
        @endforeach
    </div>

    <button type="button" id="single-page-detail-add" class="btn btn-outline-secondary btn-sm">
        {{ __('+ 詳細を追加') }}
    </button>

    <template id="single-page-detail-row-template">
        @include('admin.single_pages._detail_row', [
            'index' => '__INDEX__',
            'id' => null,
            'subTitle' => '',
            'contents' => '',
            'sortOrder' => 0,
        ])
    </template>
</div>
