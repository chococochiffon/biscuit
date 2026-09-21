<div class="modal fade" id="tag-manager-modal" tabindex="-1" aria-labelledby="tag-manager-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tag-manager-modal-label">{{ __('タグ管理') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('閉じる') }}"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="tag-manager-error" role="alert"></div>

                <div class="input-group mb-3">
                    <input
                        type="text"
                        id="tag-manager-input"
                        class="form-control"
                        placeholder="{{ __('タグ名を入力') }}"
                        autocomplete="off"
                    >
                    <button type="button" id="tag-manager-submit" class="btn btn-primary">{{ __('登録') }}</button>
                </div>

                <div
                    id="tag-manager-list"
                    class="d-flex flex-wrap gap-2"
                    data-index-url="{{ route('admin.tags.index') }}"
                ></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('閉じる') }}</button>
            </div>
        </div>
    </div>
</div>
