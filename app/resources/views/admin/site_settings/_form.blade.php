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
    <label for="front_url" class="form-label">{{ __('フロントのURL') }}</label>
    <input
        id="front_url"
        type="url"
        name="front_url"
        value="{{ old('front_url', $siteSetting->front_url ?? '') }}"
        maxlength="255"
        placeholder="https://"
        class="form-control"
    >
</div>

<div class="mb-3">
    <label for="api_url" class="form-label">{{ __('APIのURL') }}</label>
    <input
        id="api_url"
        type="url"
        name="api_url"
        value="{{ old('api_url', $siteSetting->api_url ?? '') }}"
        maxlength="255"
        placeholder="https://"
        class="form-control"
    >
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
    $oldTopSliderImages = old('top_slider_images');
    $topSliderImageUrls = ($topSliderImages ?? collect())->mapWithKeys(fn ($topSliderImage) => [$topSliderImage->id => $topSliderImage->top_image_url]);

    // 入力エラーで戻った場合、選択していた画像ファイルは引き継げないため、既存行だけ保存済みの画像を表示する
    $topSliderImageRows = $oldTopSliderImages !== null
        ? collect($oldTopSliderImages)->values()->map(fn ($row, $i) => (object) [
            'index' => (string) $i,
            'id' => $row['id'] ?? null,
            'imageUrl' => isset($row['id']) ? $topSliderImageUrls->get((int) $row['id']) : null,
            'url' => $row['url'] ?? null,
            'sortOrder' => $row['sort_order'] ?? $i,
        ])
        : ($topSliderImages ?? collect())->values()->map(fn ($topSliderImage, $i) => (object) [
            'index' => (string) $i,
            'id' => $topSliderImage->id,
            'imageUrl' => $topSliderImage->top_image_url,
            'url' => $topSliderImage->url,
            'sortOrder' => $topSliderImage->sort_order,
        ]);
@endphp

<div class="mb-3" data-role="repeater">
    <label class="form-label mb-0">{{ __('トップスライダー画像') }}</label>
    <div class="form-text mb-2">{{ __('公開側トップのスライダーに、この順で表示します。') }}</div>

    <div data-role="repeater-rows" data-next-index="{{ $topSliderImageRows->count() }}">
        @foreach ($topSliderImageRows as $row)
            @include('admin.site_settings._top_slider_image_row', [
                'index' => $row->index,
                'id' => $row->id,
                'imageUrl' => $row->imageUrl,
                'url' => $row->url,
                'sortOrder' => $row->sortOrder,
            ])
        @endforeach
    </div>

    <button type="button" class="btn btn-outline-secondary btn-sm" data-role="repeater-add">
        {{ __('+ 行を追加') }}
    </button>

    <template data-role="repeater-template">
        @include('admin.site_settings._top_slider_image_row', [
            'index' => '__INDEX__',
            'id' => null,
            'imageUrl' => null,
            'url' => null,
            'sortOrder' => 0,
        ])
    </template>
</div>

@php
    $oldSocialLinks = old('social_links');

    $socialLinkRows = $oldSocialLinks !== null
        ? collect($oldSocialLinks)->values()->map(fn ($row, $i) => (object) [
            'index' => (string) $i,
            'id' => $row['id'] ?? null,
            'service' => isset($row['service']) && $row['service'] !== '' ? (int) $row['service'] : null,
            'name' => $row['name'] ?? null,
            'url' => $row['url'] ?? null,
            'sortOrder' => $row['sort_order'] ?? $i,
        ])
        : ($socialLinks ?? collect())->values()->map(fn ($socialLink, $i) => (object) [
            'index' => (string) $i,
            'id' => $socialLink->id,
            'service' => $socialLink->service->value,
            'name' => $socialLink->name,
            'url' => $socialLink->url,
            'sortOrder' => $socialLink->sort_order,
        ]);
@endphp

<div class="mb-3" data-role="repeater">
    <label class="form-label mb-0">{{ __('SNSリンク') }}</label>
    <div class="form-text mb-2">{{ __('公開側のフッターに、この順でアイコンを並べます。') }}</div>

    <div data-role="repeater-rows" data-next-index="{{ $socialLinkRows->count() }}">
        @foreach ($socialLinkRows as $row)
            @include('admin.site_settings._social_link_row', [
                'index' => $row->index,
                'id' => $row->id,
                'service' => $row->service,
                'name' => $row->name,
                'url' => $row->url,
                'sortOrder' => $row->sortOrder,
            ])
        @endforeach
    </div>

    <button type="button" class="btn btn-outline-secondary btn-sm" data-role="repeater-add">
        {{ __('+ 行を追加') }}
    </button>

    <template data-role="repeater-template">
        @include('admin.site_settings._social_link_row', [
            'index' => '__INDEX__',
            'id' => null,
            'service' => null,
            'name' => null,
            'url' => null,
            'sortOrder' => 0,
        ])
    </template>
</div>

@php
    $oldCallContents = old('call_contents');

    $callContentRows = $oldCallContents !== null
        ? collect($oldCallContents)->values()->map(fn ($row, $i) => (object) [
            'index' => (string) $i,
            'id' => $row['id'] ?? null,
            'callType' => isset($row['call_type']) && $row['call_type'] !== '' ? (int) $row['call_type'] : null,
            'callName' => $row['call_name'] ?? null,
            'title' => $row['title'] ?? null,
            'subtitle' => $row['subtitle'] ?? null,
            'contentModelRelationId' => isset($row['content_model_relation_id']) && $row['content_model_relation_id'] !== '' ? (int) $row['content_model_relation_id'] : null,
            'viewCount' => $row['view_count'] ?? 1,
            'place' => isset($row['place']) && $row['place'] !== '' ? (int) $row['place'] : null,
            'sortOrder' => $row['sort_order'] ?? $i,
        ])
        : ($callContents ?? collect())->values()->map(fn ($callContent, $i) => (object) [
            'index' => (string) $i,
            'id' => $callContent->id,
            'callType' => $callContent->call_type->value,
            'callName' => $callContent->call_name,
            'title' => $callContent->title,
            'subtitle' => $callContent->subtitle,
            'contentModelRelationId' => $callContent->content_model_relation_id,
            'viewCount' => $callContent->view_count,
            'place' => $callContent->place->value,
            'sortOrder' => $callContent->sort_order,
        ]);
@endphp

<div class="mb-3">
    <div class="d-flex align-items-center justify-content-between">
        <label class="form-label mb-0">{{ __('API設定') }}</label>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#content-model-relation-manager-modal">
            {{ __('データ種別紐付け管理') }}
        </button>
    </div>

    <div class="form-text mb-2">{{ __('左端のハンドルをドラッグして並び替えると、同じ表示箇所の中でその順にAPIで返します。') }}</div>

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
                'title' => $row->title,
                'subtitle' => $row->subtitle,
                'contentModelRelationId' => $row->contentModelRelationId,
                'viewCount' => $row->viewCount,
                'place' => $row->place,
                'sortOrder' => $row->sortOrder,
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
            'title' => null,
            'subtitle' => null,
            'contentModelRelationId' => null,
            'viewCount' => 1,
            'place' => null,
            'sortOrder' => 0,
            'contentModelRelations' => $contentModelRelations ?? collect(),
        ])
    </template>
</div>

@include('admin.content_model_relations._manager_modal', ['tableNames' => $tableNames ?? []])
