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
    <label for="gallery_category_id" class="form-label">{{ __('分類') }}</label>
    <select id="gallery_category_id" name="gallery_category_id" class="form-select form-select-auto">
        <option value="">{{ __('未分類') }}</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((string) old('gallery_category_id', $galleryImage->gallery_category_id ?? '') === (string) $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
    <div class="form-text">
        <a href="{{ route('admin.gallery-categories.edit') }}">{{ __('分類の管理') }}</a>
    </div>
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
