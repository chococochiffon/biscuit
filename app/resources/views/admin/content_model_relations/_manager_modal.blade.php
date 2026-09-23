@php
    /**
     * @var \Illuminate\Support\Collection|array<int, string> $tableNames
     */
@endphp

<div class="modal fade" id="content-model-relation-manager-modal" tabindex="-1" aria-labelledby="content-model-relation-manager-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="content-model-relation-manager-modal-label">{{ __('データ種別紐付け管理') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('閉じる') }}"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="content-model-relation-manager-error" role="alert"></div>

                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-4">
                        <label class="form-label small">{{ __('コンテンツ種別') }}</label>
                        <select id="content-model-relation-manager-content-type" class="form-select form-select-sm">
                            @foreach (\App\Enums\CallContentType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">{{ __('モデル名') }}</label>
                        <input type="text" id="content-model-relation-manager-model-name" class="form-control form-control-sm" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">{{ __('テーブル名') }}</label>
                        <select id="content-model-relation-manager-table-name" class="form-select form-select-sm">
                            <option value="" disabled selected>{{ __('選択してください') }}</option>
                            @foreach ($tableNames as $tableName)
                                <option value="{{ $tableName }}">{{ $tableName }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="text-end mb-3">
                    <button type="button" id="content-model-relation-manager-submit" class="btn btn-primary btn-sm">{{ __('登録') }}</button>
                </div>

                <div
                    id="content-model-relation-manager-list"
                    class="list-group"
                    data-index-url="{{ route('admin.content-model-relations.index') }}"
                ></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('閉じる') }}</button>
            </div>
        </div>
    </div>
</div>
