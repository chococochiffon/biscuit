@include('admin.partials._form_errors')

<div class="mb-3">
    <label class="form-label">{{ __('画像') }}</label>
    <x-admin.image-dropzone
        id="image"
        name="image"
        :image-url="($galleryImage ?? null)?->image_url"
        :alt="__('ギャラリー画像')"
        :aria-label="__('ギャラリー画像を選択')"
        :removable="! isset($galleryImage)"
    />
    <div class="form-text">
        {{ __('長辺が:sizepxを超える画像は、比率を保ったまま縮小して保存します。', ['size' => \App\Models\GalleryImage::IMAGE_MAX_SIZE]) }}
        @isset($galleryImage)
            <br>{{ __('変更しない場合は選択不要です。') }}
        @endisset
    </div>
</div>

<div class="mb-3">
    <label for="name" class="form-label">{{ __('名前') }}</label>
    <input
        id="name"
        type="text"
        name="name"
        value="{{ old('name', $galleryImage->name ?? '') }}"
        maxlength="128"
        required
        class="form-control"
    >
</div>

<div class="mb-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <label for="gallery_category_id" class="form-label mb-0">{{ __('分類') }}</label>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#gallery-category-manager-modal">
            {{ __('分類管理') }}
        </button>
    </div>
    <select id="gallery_category_id" name="gallery_category_id" class="form-select form-select-auto" data-role="gallery-category-select">
        <option value="">{{ __('未分類') }}</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((string) old('gallery_category_id', $galleryImage->gallery_category_id ?? '') === (string) $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="comment" class="form-label">{{ __('コメント') }}</label>
    <input
        id="comment"
        type="text"
        name="comment"
        value="{{ old('comment', $galleryImage->comment ?? '') }}"
        maxlength="255"
        class="form-control"
    >
</div>

@isset($galleryImage)
    <div class="mb-3">
        <label for="approval" class="form-label">{{ __('公開ステータス') }}</label>
        <select id="approval" name="approval" required class="form-select form-select-auto">
            @foreach (\App\Enums\ArticleApprovalStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(old('approval', $galleryImage->approval->value) === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
        <div class="form-text">{{ __('投稿者: :name', ['name' => $galleryImage->user_name]) }}</div>
    </div>

    @if ($galleryImage->user_id !== null)
        <div class="mb-3">
            <label for="review_comment" class="form-label">{{ __('差し戻しの理由') }}</label>
            <textarea id="review_comment" name="review_comment" rows="3" class="form-control" maxlength="2000">{{ old('review_comment', $galleryImage->review_comment) }}</textarea>
            <div class="form-text">{{ __('ユーザーの画像を下書きに戻すときに入力すると、マイページに表示されます。ユーザーが承認を申請し直すと消えます。') }}</div>
        </div>
    @endif
@endisset
