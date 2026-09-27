@php
    /**
     * @var string $index
     * @var int|string|null $id
     * @var string|null $imageUrl
     * @var string|null $url
     * @var int $sortOrder
     */
@endphp

<div class="row g-2 mb-2 border-bottom pb-2" data-role="repeater-row">
    @if ($id)
        <input type="hidden" name="top_slider_images[{{ $index }}][id]" value="{{ $id }}">
    @endif
    <input type="hidden" name="top_slider_images[{{ $index }}][sort_order]" value="{{ $sortOrder }}" data-role="sort-order">

    <div class="col-auto align-self-stretch d-flex">
        <span class="single-page-detail-handle" data-role="drag-handle" title="{{ __('ドラッグして並び替え') }}">
            <i class="bi bi-grip-vertical"></i>
        </span>
    </div>

    <div
        class="col-md-6"
        data-role="image-cropper"
        data-output-width="{{ \App\Models\TopSliderImage::IMAGE_WIDTH }}"
        data-output-height="{{ \App\Models\TopSliderImage::IMAGE_HEIGHT }}"
    >
        <label class="form-label small">{{ __('画像') }}</label>
        <x-admin.image-dropzone
            name="top_slider_images[{{ $index }}][image]"
            :image-url="$imageUrl"
            :alt="__('トップスライダー画像')"
            :aria-label="__('トップスライダー画像を選択')"
            :removable="false"
        />
        <input type="hidden" name="top_slider_images[{{ $index }}][crop_x]" data-role="image-cropper-x">
        <input type="hidden" name="top_slider_images[{{ $index }}][crop_y]" data-role="image-cropper-y">
        <input type="hidden" name="top_slider_images[{{ $index }}][crop_width]" data-role="image-cropper-width">
        <input type="hidden" name="top_slider_images[{{ $index }}][crop_height]" data-role="image-cropper-height">

        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-role="image-cropper-edit" style="display: none;">
            <i class="bi bi-crop"></i> {{ __('切り抜きを編集') }}
        </button>
        <div class="form-text">{{ __('画像を選ぶと切り抜き画面が開きます(:sizeで保存します)。', ['size' => \App\Models\TopSliderImage::IMAGE_WIDTH.'×'.\App\Models\TopSliderImage::IMAGE_HEIGHT]) }}</div>
    </div>

    <div class="col">
        <label class="form-label small">{{ __('リンク先URL') }}</label>
        <input
            type="url"
            name="top_slider_images[{{ $index }}][url]"
            value="{{ $url }}"
            class="form-control form-control-sm"
            maxlength="255"
            placeholder="https://"
        >
    </div>

    <div class="col-auto align-self-start">
        <label class="form-label small d-block">&nbsp;</label>
        <button type="button" class="btn btn-outline-danger btn-sm" data-role="remove-row">−</button>
    </div>
</div>
