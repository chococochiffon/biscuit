@extends('layouts.admin')

@section('title', __('レイアウト管理'))

@section('content')
    @php
        $blockRows = \App\Support\RepeaterRows::build(
            'blocks',
            $blocks,
            fn (array $row) => [
                'region' => \App\Support\RepeaterRows::intOrNull($row['region'] ?? null),
                'blockType' => \App\Support\RepeaterRows::intOrNull($row['block_type'] ?? null),
                'title' => $row['title'] ?? null,
                'subtitle' => $row['subtitle'] ?? null,
                'callType' => \App\Support\RepeaterRows::intOrNull($row['call_type'] ?? null),
                'contentModelRelationId' => \App\Support\RepeaterRows::intOrNull($row['content_model_relation_id'] ?? null),
                'viewCount' => $row['view_count'] ?? 1,
                'content' => $row['content'] ?? null,
            ],
            fn ($block) => [
                'region' => $block->region->value,
                'blockType' => $block->block_type->value,
                'title' => $block->title,
                'subtitle' => $block->subtitle,
                'callType' => $block->call_type?->value,
                'contentModelRelationId' => $block->content_model_relation_id,
                'viewCount' => $block->view_count ?? 1,
                'content' => $block->content,
            ],
        );
        $regionDescriptions = [
            \App\Enums\LayoutRegion::Header->value => __('全ページの上部に、左から順に並べます。'),
            \App\Enums\LayoutRegion::Sidebar->value => __('サイドバーを表示するページ(上の設定)で、本文の横に上から順に並べます。'),
            \App\Enums\LayoutRegion::Footer->value => __('全ページの下部に並べます。見出しを持つ部品は上段に列で、それ以外(サイトタイトル・コピーライト・SNSリンクなど)は下段に並べます。'),
        ];
    @endphp

    <div class="mx-auto" style="max-width: 80rem;">
        <div class="mb-4 d-flex align-items-center justify-content-between">
            <h1 class="h5 mb-0">{{ __('レイアウト管理') }}</h1>
            @if ($frontUrl)
                <a href="{{ $frontUrl }}" target="_blank" rel="noopener" class="link-primary">
                    {{ __('フロントで確認') }} <i class="bi bi-box-arrow-up-right"></i>
                </a>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.layouts.update') }}">
            @csrf
            @method('PUT')

            @include('admin.partials._form_errors')

            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h6">{{ __('ページの種類ごとのサイドバーの位置') }}</h2>
                    <div class="form-text mb-3">{{ __('カスタムページは、記事型を「記事」、固定ページ型を「固定ページ」として扱います。') }}</div>

                    <div class="row g-3">
                        @foreach (\App\Enums\LayoutPageType::cases() as $pageType)
                            @php
                                $sidebarPosition = \App\Support\RepeaterRows::intOrNull(old("layouts.{$pageType->value}.sidebar_position"))
                                    ?? $sidebarPositions[$pageType->value]->value;
                            @endphp
                            <div class="col-sm-6 col-lg-3">
                                <label for="layout-sidebar-{{ $pageType->value }}" class="form-label small">{{ $pageType->label() }}</label>
                                <select
                                    id="layout-sidebar-{{ $pageType->value }}"
                                    name="layouts[{{ $pageType->value }}][sidebar_position]"
                                    class="form-select form-select-sm form-select-auto"
                                    required
                                >
                                    @foreach (\App\Enums\SidebarPosition::cases() as $position)
                                        <option value="{{ $position->value }}" @selected($sidebarPosition === $position->value)>{{ $position->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div
                id="layout-blocks"
                data-next-index="{{ $blockRows->count() }}"
                data-call-type-constraints="{{ json_encode(\App\Enums\CallType::jsConstraintsMap()) }}"
                data-block-types="{{ json_encode([
                    'callContent' => \App\Enums\LayoutBlockType::CallContent->value,
                    'freeText' => \App\Enums\LayoutBlockType::FreeText->value,
                    'withHeading' => collect(\App\Enums\LayoutBlockType::cases())->filter->hasHeading()->map->value->values(),
                ]) }}"
            >
                <div class="form-text mb-2">{{ __('左端のハンドルをドラッグすると、領域の中で並び替えたり、別の領域へ移したりできます。呼び出しコンテンツの部品は、サイト設定の呼び出しコンテンツ(表示箇所: その他)と同じ組み合わせで選べます。') }}</div>

                @foreach (\App\Enums\LayoutRegion::cases() as $region)
                    <div class="card mb-4">
                        <div class="card-body">
                            <h2 class="h6 mb-1">{{ $region->label() }}</h2>
                            <div class="form-text mb-2">{{ $regionDescriptions[$region->value] }}</div>

                            <div class="layout-block-rows" data-role="layout-block-rows" data-region="{{ $region->value }}">
                                @foreach ($blockRows->where('region', $region->value) as $row)
                                    @include('admin.layouts._block_row', [
                                        'index' => $row->index,
                                        'id' => $row->id,
                                        'region' => $row->region,
                                        'blockType' => $row->blockType,
                                        'title' => $row->title,
                                        'subtitle' => $row->subtitle,
                                        'callType' => $row->callType,
                                        'contentModelRelationId' => $row->contentModelRelationId,
                                        'viewCount' => $row->viewCount,
                                        'content' => $row->content,
                                        'sortOrder' => $row->sortOrder,
                                        'contentModelRelations' => $contentModelRelations,
                                    ])
                                @endforeach
                            </div>

                            <button type="button" class="btn btn-outline-secondary btn-sm" data-role="layout-block-add" data-region="{{ $region->value }}">
                                {{ __('+ 部品を追加') }}
                            </button>
                        </div>
                    </div>
                @endforeach

                <template id="layout-block-row-template">
                    @include('admin.layouts._block_row', [
                        'index' => '__INDEX__',
                        'id' => null,
                        'region' => null,
                        'blockType' => null,
                        'title' => null,
                        'subtitle' => null,
                        'callType' => null,
                        'contentModelRelationId' => null,
                        'viewCount' => 1,
                        'content' => null,
                        'sortOrder' => 0,
                        'contentModelRelations' => $contentModelRelations,
                    ])
                </template>
            </div>

            <div class="d-flex align-items-center gap-3">
                <button type="submit" class="btn btn-primary">
                    {{ __('更新する') }}
                </button>
            </div>
        </form>
    </div>
@endsection
