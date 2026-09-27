@props([
    'name',
    'alt',
    'ariaLabel',
    'id' => null,
    'imageUrl' => null,
    'icon' => false,
    'removable' => true,
])

{{--
    画像アップロード用のドロップゾーン。クリック(Enter/Space)またはドラッグ&ドロップで画像を選び、選んだ画像を枠内にプレビューする
    (admin.js の initImageDropzones())。data-role="image-cropper" の中に置くと、選んだ画像を切り抜きモーダルで調整する(initImageCroppers())。
    imageUrl: 保存済みの画像の URL(なければ「クリックまたはドラッグ&ドロップ」の案内を表示する)
    icon: 正方形の小さな枠にする / removable: 選択を解除するボタンを表示する
--}}
<div
    {{ $attributes->class(['image-dropzone', 'image-dropzone--icon' => $icon]) }}
    data-role="image-dropzone"
    tabindex="0"
    role="button"
    aria-label="{{ $ariaLabel }}"
>
    <input @if ($id) id="{{ $id }}" @endif type="file" name="{{ $name }}" accept="image/*" class="d-none" data-role="image-dropzone-input">

    <div class="image-dropzone-preview" data-role="image-dropzone-preview" @if (! $imageUrl) style="display: none;" @endif>
        <img src="{{ $imageUrl }}" data-original-src="{{ $imageUrl }}" alt="{{ $alt }}" data-role="image-dropzone-image">
        @if ($removable)
            <button type="button" class="btn btn-sm btn-outline-secondary image-dropzone-remove" data-role="image-dropzone-remove" aria-label="{{ __('選択を解除') }}">
                <i class="bi bi-x-lg"></i>
            </button>
        @endif
    </div>

    <div class="image-dropzone-placeholder" data-role="image-dropzone-placeholder" @if ($imageUrl) style="display: none;" @endif>
        <i class="bi bi-cloud-arrow-up"></i>
        <span class="small">{{ __('クリックまたはドラッグ&ドロップ') }}</span>
    </div>
</div>
