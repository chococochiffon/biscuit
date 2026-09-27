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
        <div class="image-dropzone" data-role="image-cropper-dropzone" tabindex="0" role="button" aria-label="{{ __('トップスライダー画像を選択') }}">
            <input
                type="file"
                name="top_slider_images[{{ $index }}][image]"
                accept="image/*"
                class="d-none"
                data-role="image-cropper-input"
            >

            <div class="image-dropzone-preview" data-role="image-cropper-frame" @if (! $imageUrl) style="display: none;" @endif>
                <img src="{{ $imageUrl }}" data-original-src="{{ $imageUrl }}" alt="{{ __('トップスライダー画像') }}" data-role="image-cropper-image">
            </div>

            <div class="image-dropzone-placeholder" data-role="image-cropper-placeholder" @if ($imageUrl) style="display: none;" @endif>
                <i class="bi bi-cloud-arrow-up"></i>
                <span class="small">{{ __('クリックまたはドラッグ&ドロップ') }}</span>
            </div>
        </div>
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
