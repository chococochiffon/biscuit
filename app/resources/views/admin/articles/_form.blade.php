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
        value="{{ old('title', $article->title ?? '') }}"
        required
        class="form-control"
    >
</div>

@php
    $thumbnailUrl = $article->thumbnail_url ?? Illuminate\Support\Facades\Storage::disk('public')->url(\App\Models\Article::DEFAULT_THUMBNAIL_PATH);
@endphp

<div class="mb-3">
    <label class="form-label">{{ __('サムネイル画像') }}</label>
    <div class="image-dropzone" data-role="image-dropzone" tabindex="0" role="button" aria-label="{{ __('サムネイル画像を選択') }}">
        <input id="thumbnail" type="file" name="thumbnail" accept="image/*" class="d-none" data-role="image-dropzone-input">

        <div class="image-dropzone-preview" data-role="image-dropzone-preview">
            <img src="{{ $thumbnailUrl }}" alt="{{ __('サムネイル') }}" data-role="image-dropzone-image">
            <button type="button" class="btn btn-sm btn-outline-secondary image-dropzone-remove" data-role="image-dropzone-remove" aria-label="{{ __('選択を解除') }}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="image-dropzone-placeholder" data-role="image-dropzone-placeholder" style="display: none;">
            <i class="bi bi-cloud-arrow-up"></i>
            <span class="small">{{ __('クリックまたはドラッグ&ドロップ') }}</span>
        </div>
    </div>
    <div class="form-text">{{ __('未指定の場合はデフォルト画像が使用されます。') }}</div>
</div>

<div class="mb-3">
    <div class="d-flex align-items-center justify-content-between">
        <label for="tag-input" class="form-label mb-0">{{ __('タグ') }}</label>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#tag-manager-modal">
            {{ __('タグ管理') }}
        </button>
    </div>
    <div
        id="tag-selector"
        class="position-relative mt-2"
        data-search-url="{{ route('admin.tags.search') }}"
        data-initial-tags="{{ ($article->tags ?? collect())->pluck('tag_name')->toJson() }}"
    >
        <div id="selected-tags" class="d-flex flex-wrap gap-2 mb-2"></div>
        <div id="tag-hidden-inputs"></div>
        <input type="text" id="tag-input" class="form-control" placeholder="{{ __('タグ名を入力して検索...') }}" autocomplete="off">
        <div id="tag-suggestions" class="list-group position-absolute w-100" style="z-index: 1000;"></div>
    </div>
</div>

@include('admin.tags._manager_modal')

@isset($article)
    <div class="mb-3">
        <label for="approval" class="form-label">{{ __('公開ステータス') }}</label>
        <select id="approval" name="approval" required class="form-select">
            @foreach (\App\Enums\ArticleApprovalStatus::cases() as $status)
                <option
                    value="{{ $status->value }}"
                    @selected(old('approval', $article->approval->value) === $status->value)
                >
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
    </div>
@endisset

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="publication_start_datetime" class="form-label">{{ __('公開開始') }}</label>
        <input
            id="publication_start_datetime"
            type="text"
            name="publication_start_datetime"
            value="{{ old('publication_start_datetime', optional($article->publication_start_datetime ?? now())->format('Y-m-d H:i')) }}"
            required
            class="form-control"
            data-role="datetime-picker"
            autocomplete="off"
        >
    </div>

    <div class="col-md-6 mb-3">
        <label for="publication_end_datetime" class="form-label">{{ __('公開終了') }}</label>
        <input
            id="publication_end_datetime"
            type="text"
            name="publication_end_datetime"
            value="{{ old('publication_end_datetime', optional($article->publication_end_datetime ?? null)->format('Y-m-d H:i')) }}"
            class="form-control"
            data-role="datetime-picker"
            autocomplete="off"
        >
        <div class="form-text">{{ __('未指定の場合は終了日時を設定しません。') }}</div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label is-required">{{ __('本文') }}</label>
    <div
        id="content-editor"
        data-upload-url="{{ route('admin.articles.content-images') }}"
        style="height: 320px;"
    ></div>
    <textarea id="content-input" name="content" hidden>{{ old('content', $article->content ?? '') }}</textarea>
</div>
