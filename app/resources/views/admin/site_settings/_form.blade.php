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

<div class="mb-3">
    <label for="site_icon" class="form-label">{{ __('サイトアイコン') }}</label>
    @isset($siteSetting)
        @if ($siteSetting->site_icon)
            <div class="mb-2">
                <img
                    src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($siteSetting->site_icon) }}"
                    alt="{{ __('サイトアイコン') }}"
                    class="img-thumbnail"
                    style="width: 96px; height: 96px; object-fit: cover;"
                >
            </div>
        @endif
    @endisset
    <input id="site_icon" type="file" name="site_icon" accept="image/*" class="form-control">
</div>

<div class="mb-3">
    <label for="site_image" class="form-label">{{ __('サイト画像') }}</label>
    @isset($siteSetting)
        @if ($siteSetting->site_image)
            <div class="mb-2">
                <img
                    src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($siteSetting->site_image) }}"
                    alt="{{ __('サイト画像') }}"
                    class="img-thumbnail"
                    style="width: 240px; height: 160px; object-fit: cover;"
                >
            </div>
        @endif
    @endisset
    <input id="site_image" type="file" name="site_image" accept="image/*" class="form-control">
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
    <label class="form-label">{{ __('API設定') }}</label>

    <div
        id="call-content-rows"
        data-next-index="{{ $callContentRows->count() }}"
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
