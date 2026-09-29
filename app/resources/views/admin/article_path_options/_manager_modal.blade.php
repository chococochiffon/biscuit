{{-- ユーザーが記事を投稿するときに選ぶ投稿先(親パス)を管理するモーダル(登録・変更・削除・ドラッグでの並び替えを Ajax で行う) --}}
<div class="modal fade" id="article-path-option-manager-modal" tabindex="-1" aria-labelledby="article-path-option-manager-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="article-path-option-manager-modal-label">{{ __('投稿先管理') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('閉じる') }}"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="article-path-option-manager-error" role="alert"></div>

                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-5">
                        <label for="article-path-option-manager-label" class="form-label small">{{ __('表示名') }}</label>
                        <input type="text" id="article-path-option-manager-label" class="form-control form-control-sm" maxlength="128" autocomplete="off" placeholder="{{ __('例: 技術ブログ') }}">
                    </div>
                    <div class="col-md-5">
                        <label for="article-path-option-manager-parent-path" class="form-label small">{{ __('親パス') }}</label>
                        <input type="text" id="article-path-option-manager-parent-path" class="form-control form-control-sm" maxlength="255" autocomplete="off" placeholder="{{ __('例: blog/tech') }}">
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" id="article-path-option-manager-submit" class="btn btn-primary btn-sm">{{ __('登録') }}</button>
                    </div>
                </div>

                <div class="form-text mb-2">
                    {{ __('ユーザーはマイページで記事を投稿するときに、ここで登録した投稿先から選びます(上限 :max 件)。名前をクリックすると変更でき、ドラッグした順に選択肢が並びます。変更・削除しても、投稿済みの記事の URL は変わりません。', ['max' => config('limits.article_path_options')]) }}
                </div>

                <ul
                    id="article-path-option-manager-list"
                    class="list-group"
                    data-index-url="{{ route('admin.article-path-options.index') }}"
                    data-reorder-url="{{ route('admin.article-path-options.reorder') }}"
                ></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('閉じる') }}</button>
            </div>
        </div>
    </div>
</div>
