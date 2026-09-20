@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mb-3">
    <label for="content_type" class="form-label">{{ __('コンテンツ種別') }}</label>
    <select id="content_type" name="content_type" required class="form-select">
        @foreach (\App\Enums\CallContentType::cases() as $type)
            <option
                value="{{ $type->value }}"
                @selected(old('content_type', $contentModelRelation->content_type->value ?? '') == $type->value)
            >
                {{ $type->label() }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="model_name" class="form-label">{{ __('モデル名') }}</label>
    <input
        id="model_name"
        type="text"
        name="model_name"
        value="{{ old('model_name', $contentModelRelation->model_name ?? '') }}"
        required
        class="form-control"
    >
</div>

<div class="mb-3">
    <label for="table_name" class="form-label">{{ __('テーブル名') }}</label>
    <select id="table_name" name="table_name" required class="form-select">
        <option value="" disabled @selected(old('table_name', $contentModelRelation->table_name ?? '') === '')>
            {{ __('選択してください') }}
        </option>
        @foreach ($tableNames as $tableName)
            <option value="{{ $tableName }}" @selected(old('table_name', $contentModelRelation->table_name ?? '') === $tableName)>
                {{ $tableName }}
            </option>
        @endforeach
    </select>
</div>
