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

<div class="mb-3">
    <label class="form-label">{{ __('サムネイル画像') }}</label>
    <div class="mb-2">
        <img
            src="{{ $article->thumbnail_url ?? Illuminate\Support\Facades\Storage::disk('public')->url(\App\Models\Article::DEFAULT_THUMBNAIL_PATH) }}"
            alt="{{ __('サムネイル') }}"
            class="img-thumbnail"
            style="width: 160px; height: 160px; object-fit: cover;"
        >
    </div>
    <input id="thumbnail" type="file" name="thumbnail" accept="image/*" class="form-control">
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

<div class="mb-3">
    <label class="form-label">{{ __('本文') }}</label>
    <div
        id="content-editor"
        data-upload-url="{{ route('admin.articles.content-images') }}"
        style="height: 320px;"
    ></div>
    <textarea id="content-input" name="content" hidden>{{ old('content', $article->content ?? '') }}</textarea>
</div>
