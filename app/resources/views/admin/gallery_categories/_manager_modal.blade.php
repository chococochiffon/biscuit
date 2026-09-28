@php
    /**
     * ギャラリー画像の分類を管理するモーダル(登録・名前の変更・削除・ドラッグでの並び替えを Ajax で行う)。
     * 変更は画面の分類の選択肢(data-role="gallery-category-select")にすぐ反映する。
     *
     * @var bool $reloadOnChange 変更があった場合、閉じたときに画面を再読み込みする(一覧の分類名を最新にする)
     */
    $reloadOnChange ??= false;
@endphp

<div
    class="modal fade"
    id="gallery-category-manager-modal"
    tabindex="-1"
    aria-labelledby="gallery-category-manager-modal-label"
    aria-hidden="true"
    @if ($reloadOnChange) data-reload-on-change="true" @endif
>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gallery-category-manager-modal-label">{{ __('分類管理') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('閉じる') }}"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="gallery-category-manager-error" role="alert"></div>

                <div class="input-group mb-3">
                    <input
                        type="text"
                        id="gallery-category-manager-input"
                        class="form-control"
                        placeholder="{{ __('分類名を入力') }}"
                        maxlength="128"
                        autocomplete="off"
                    >
                    <button type="button" id="gallery-category-manager-submit" class="btn btn-primary">{{ __('登録') }}</button>
                </div>

                <div class="form-text mb-2">
                    {{ __('名前をクリックすると変更できます。ドラッグした順に、公開側のギャラリーページの分類の切り替えが並びます。分類を削除すると、その分類の画像は未分類になります。') }}
                </div>

                <ul
                    id="gallery-category-manager-list"
                    class="list-group"
                    data-index-url="{{ route('admin.gallery-categories.index') }}"
                    data-reorder-url="{{ route('admin.gallery-categories.reorder') }}"
                ></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('閉じる') }}</button>
            </div>
        </div>
    </div>
</div>
