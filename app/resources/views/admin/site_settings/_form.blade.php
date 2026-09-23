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
    <label for="site_title" class="form-label">{{ __('サイトタイトル') }}</label>
    <input
        id="site_title"
        type="text"
        name="site_title"
        value="{{ old('site_title', $siteSetting->site_title ?? '') }}"
        required
        class="form-control"
    >
</div>

<div class="mb-3">
    <label for="description" class="form-label">{{ __('説明') }}</label>
    <textarea id="description" name="description" rows="4" class="form-control">{{ old('description', $siteSetting->description ?? '') }}</textarea>
</div>

@php
    $existingSiteIconUrl = isset($siteSetting) && $siteSetting->site_icon
        ? Illuminate\Support\Facades\Storage::disk('public')->url($siteSetting->site_icon)
        : null;

    $existingSiteImageUrl = isset($siteSetting) && $siteSetting->site_image
        ? Illuminate\Support\Facades\Storage::disk('public')->url($siteSetting->site_image)
        : null;
@endphp

<div class="mb-3">
    <label class="form-label">{{ __('サイトアイコン') }}</label>
    <div class="image-dropzone image-dropzone--icon" data-role="image-dropzone" tabindex="0" role="button" aria-label="{{ __('サイトアイコンを選択') }}">
        <input id="site_icon" type="file" name="site_icon" accept="image/*" class="d-none" data-role="image-dropzone-input">

        <div class="image-dropzone-preview" data-role="image-dropzone-preview" @if (! $existingSiteIconUrl) style="display: none;" @endif>
            <img src="{{ $existingSiteIconUrl }}" alt="{{ __('サイトアイコン') }}" data-role="image-dropzone-image">
            <button type="button" class="btn btn-sm btn-outline-secondary image-dropzone-remove" data-role="image-dropzone-remove" aria-label="{{ __('選択を解除') }}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="image-dropzone-placeholder" data-role="image-dropzone-placeholder" @if ($existingSiteIconUrl) style="display: none;" @endif>
            <i class="bi bi-cloud-arrow-up"></i>
            <span class="small">{{ __('クリックまたはドラッグ&ドロップ') }}</span>
        </div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">{{ __('サイト画像') }}</label>
    <div class="image-dropzone" data-role="image-dropzone" tabindex="0" role="button" aria-label="{{ __('サイト画像を選択') }}">
        <input id="site_image" type="file" name="site_image" accept="image/*" class="d-none" data-role="image-dropzone-input">

        <div class="image-dropzone-preview" data-role="image-dropzone-preview" @if (! $existingSiteImageUrl) style="display: none;" @endif>
            <img src="{{ $existingSiteImageUrl }}" alt="{{ __('サイト画像') }}" data-role="image-dropzone-image">
            <button type="button" class="btn btn-sm btn-outline-secondary image-dropzone-remove" data-role="image-dropzone-remove" aria-label="{{ __('選択を解除') }}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="image-dropzone-placeholder" data-role="image-dropzone-placeholder" @if ($existingSiteImageUrl) style="display: none;" @endif>
            <i class="bi bi-cloud-arrow-up"></i>
            <span class="small">{{ __('クリックまたはドラッグ&ドロップ') }}</span>
        </div>
    </div>
</div>

@php
    $oldCallContents = old('call_contents');

    $callContentRows = $oldCallContents !== null
        ? collect($oldCallContents)->values()->map(fn ($row, $i) => (object) [
            'index' => (string) $i,
            'id' => $row['id'] ?? null,
            'callType' => isset($row['call_type']) && $row['call_type'] !== '' ? (int) $row['call_type'] : null,
            'callName' => $row['call_name'] ?? null,
            'contentModelRelationId' => isset($row['content_model_relation_id']) && $row['content_model_relation_id'] !== '' ? (int) $row['content_model_relation_id'] : null,
            'viewCount' => $row['view_count'] ?? 1,
            'place' => isset($row['place']) && $row['place'] !== '' ? (int) $row['place'] : null,
        ])
        : ($callContents ?? collect())->values()->map(fn ($callContent, $i) => (object) [
            'index' => (string) $i,
            'id' => $callContent->id,
            'callType' => $callContent->call_type->value,
            'callName' => $callContent->call_name,
            'contentModelRelationId' => $callContent->content_model_relation_id,
            'viewCount' => $callContent->view_count,
            'place' => $callContent->place->value,
        ]);
@endphp

<div class="mb-3">
    <div class="d-flex align-items-center justify-content-between">
        <label class="form-label mb-0">{{ __('API設定') }}</label>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#content-model-relation-manager-modal">
            {{ __('データ種別紐付け管理') }}
        </button>
    </div>

    <div
        id="call-content-rows"
        data-next-index="{{ $callContentRows->count() }}"
        data-call-type-constraints="{{ json_encode(\App\Enums\CallType::jsConstraintsMap()) }}"
    >
        @foreach ($callContentRows as $row)
            @include('admin.site_settings._call_content_row', [
                'index' => $row->index,
                'id' => $row->id,
                'callType' => $row->callType,
                'callName' => $row->callName,
                'contentModelRelationId' => $row->contentModelRelationId,
                'viewCount' => $row->viewCount,
                'place' => $row->place,
                'contentModelRelations' => $contentModelRelations ?? collect(),
            ])
        @endforeach
    </div>

    <button type="button" id="call-content-add" class="btn btn-outline-secondary btn-sm">
        {{ __('+ 行を追加') }}
    </button>

    <template id="call-content-row-template">
        @include('admin.site_settings._call_content_row', [
            'index' => '__INDEX__',
            'id' => null,
            'callType' => null,
            'callName' => null,
            'contentModelRelationId' => null,
            'viewCount' => 1,
            'place' => null,
            'contentModelRelations' => $contentModelRelations ?? collect(),
        ])
    </template>
</div>

@include('admin.content_model_relations._manager_modal', ['tableNames' => $tableNames ?? []])
