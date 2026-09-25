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

    <div class="col-md-6" data-role="image-cropper">
        <label class="form-label small">{{ __('画像') }}</label>
        <input
            type="file"
            name="top_slider_images[{{ $index }}][image]"
            accept="image/*"
            class="form-control form-control-sm"
            data-role="image-cropper-input"
            @required(! $id)
        >
        <input type="hidden" name="top_slider_images[{{ $index }}][crop_x]" data-role="image-cropper-x">
        <input type="hidden" name="top_slider_images[{{ $index }}][crop_y]" data-role="image-cropper-y">
        <input type="hidden" name="top_slider_images[{{ $index }}][crop_width]" data-role="image-cropper-width">
        <input type="hidden" name="top_slider_images[{{ $index }}][crop_height]" data-role="image-cropper-height">

        <div class="image-cropper-frame mt-2" data-role="image-cropper-frame" @if (! $imageUrl) style="display: none;" @endif>
            <img src="{{ $imageUrl }}" data-original-src="{{ $imageUrl }}" alt="{{ __('トップスライダー画像') }}" data-role="image-cropper-image">
        </div>
        <div class="form-text">{{ __('画像を選ぶと16:9の枠が表示されます。枠の移動・拡大縮小で表示する範囲を調整してください(1920×1080で保存します)。') }}</div>
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
