{{--
    画像の切り抜き用モーダル(フォームの data-role="image-cropper" で画像を選ぶと admin.js の initImageCroppers() が開く)。
    1 画面に 1 つだけ置き、どの入力欄の画像もこのモーダルで切り抜く。
--}}
<div
    class="modal fade"
    id="image-cropper-modal"
    tabindex="-1"
    aria-labelledby="image-cropper-modal-label"
    aria-hidden="true"
    data-bs-backdrop="static"
>
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="image-cropper-modal-label">{{ __('画像の切り抜き') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('閉じる') }}"></button>
            </div>
            <div class="modal-body">
                <p
                    class="form-text mt-0"
                    data-role="image-cropper-modal-help"
                    data-template="{{ __('枠の移動・大きさの変更で切り抜く範囲を、拡大・縮小ボタンやマウスホイールで画像の大きさを調整してください(:sizeで保存します)。') }}"
                ></p>
                <div class="image-cropper-modal-canvas">
                    <img alt="" data-role="image-cropper-modal-image">
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" data-role="image-cropper-modal-zoom" data-zoom-ratio="0.1" title="{{ __('拡大') }}" aria-label="{{ __('拡大') }}">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-role="image-cropper-modal-zoom" data-zoom-ratio="-0.1" title="{{ __('縮小') }}" aria-label="{{ __('縮小') }}">
                        <i class="bi bi-zoom-out"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-role="image-cropper-modal-reset" title="{{ __('元に戻す') }}" aria-label="{{ __('元に戻す') }}">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('キャンセル') }}</button>
                    <button type="button" class="btn btn-primary" data-role="image-cropper-modal-apply">{{ __('決定') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
