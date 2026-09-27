@include('admin.partials._form_errors')

{{-- 左: 基本設定・画像 / 右: トップスライダー画像・SNSリンク（API設定は下に全幅で表示） --}}
<div class="row g-4 mb-3">
    <div class="col-lg-6">
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
            $existingSiteIconUrl = ($siteSetting ?? null)?->site_icon ? $siteSetting->site_icon_url : null;

            $existingSiteImageUrl = ($siteSetting ?? null)?->site_image ? $siteSetting->site_image_url : null;
        @endphp

        <div class="mb-3">
            <label class="form-label">{{ __('サイトアイコン') }}</label>
            <x-admin.image-dropzone
                id="site_icon"
                name="site_icon"
                :image-url="$existingSiteIconUrl"
                :alt="__('サイトアイコン')"
                :aria-label="__('サイトアイコンを選択')"
                icon
            />
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('サイト画像') }}</label>
            <x-admin.image-dropzone
                id="site_image"
                name="site_image"
                :image-url="$existingSiteImageUrl"
                :alt="__('サイト画像')"
                :aria-label="__('サイト画像を選択')"
            />
        </div>
    </div>

    <div class="col-lg-6">
        @php
            $topSliderImageUrls = ($topSliderImages ?? collect())->mapWithKeys(fn ($topSliderImage) => [$topSliderImage->id => $topSliderImage->top_image_url]);

            // 入力エラーで戻った場合、選択していた画像ファイルは引き継げないため、既存行だけ保存済みの画像を表示する
            $topSliderImageRows = \App\Support\RepeaterRows::build(
                'top_slider_images',
                $topSliderImages ?? [],
                fn (array $row) => [
                    'imageUrl' => isset($row['id']) ? $topSliderImageUrls->get((int) $row['id']) : null,
                    'url' => $row['url'] ?? null,
                ],
                fn ($topSliderImage) => [
                    'imageUrl' => $topSliderImage->top_image_url,
                    'url' => $topSliderImage->url,
                ],
            );
        @endphp

        <div class="mb-3" data-role="repeater" data-max-rows="{{ config('limits.top_slider_images') }}">
            <label class="form-label mb-0">{{ __('トップスライダー画像') }}</label>
            <div class="form-text mb-2">
                {{ __('公開側トップのスライダーに、この順で表示します。') }}
                {{ __('最大:max件まで登録できます。', ['max' => config('limits.top_slider_images')]) }}
            </div>

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
            $socialLinkRows = \App\Support\RepeaterRows::build(
                'social_links',
                $socialLinks ?? [],
                fn (array $row) => [
                    'service' => \App\Support\RepeaterRows::intOrNull($row['service'] ?? null),
                    'name' => $row['name'] ?? null,
                    'url' => $row['url'] ?? null,
                ],
                fn ($socialLink) => [
                    'service' => $socialLink->service->value,
                    'name' => $socialLink->name,
                    'url' => $socialLink->url,
                ],
            );
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
    </div>
</div>

@php
    $callContentRows = \App\Support\RepeaterRows::build(
        'call_contents',
        $callContents ?? [],
        fn (array $row) => [
            'callType' => \App\Support\RepeaterRows::intOrNull($row['call_type'] ?? null),
            'callName' => $row['call_name'] ?? null,
            'title' => $row['title'] ?? null,
            'subtitle' => $row['subtitle'] ?? null,
            'contentModelRelationId' => \App\Support\RepeaterRows::intOrNull($row['content_model_relation_id'] ?? null),
            'viewCount' => $row['view_count'] ?? 1,
            'place' => \App\Support\RepeaterRows::intOrNull($row['place'] ?? null),
        ],
        fn ($callContent) => [
            'callType' => $callContent->call_type->value,
            'callName' => $callContent->call_name,
            'title' => $callContent->title,
            'subtitle' => $callContent->subtitle,
            'contentModelRelationId' => $callContent->content_model_relation_id,
            'viewCount' => $callContent->view_count,
            'place' => $callContent->place->value,
        ],
    );
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

@include('admin.partials._image_cropper_modal')
