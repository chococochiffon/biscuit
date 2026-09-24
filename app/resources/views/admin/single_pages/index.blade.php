@extends('layouts.admin')

@section('title', __('固定ページ一覧'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('固定ページ一覧') }}</h1>

        <div class="d-flex align-items-center gap-2">
            @if ($canReorder && $singlePages->isNotEmpty())
                <button type="submit" form="single-page-reorder-form" class="btn btn-outline-secondary btn-sm">
                    {{ __('並び替えを保存') }}
                </button>
            @elseif (! $canReorder)
                <a
                    href="{{ route('admin.single-pages.index', ['sort' => 'sort_order']) }}"
                    class="btn btn-outline-secondary btn-sm"
                    title="{{ __('検索条件をクリアして表示順で並べ、ドラッグで並び替えられるようにします。') }}"
                >
                    {{ __('表示順で並び替え') }}
                </a>
            @endif

            <a href="{{ route('admin.single-pages.create') }}" class="btn btn-primary">
                {{ __('新規登録') }}
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.single-pages.index') }}" class="card mb-3 admin-search-card">
        <input type="hidden" name="sort" value="{{ $sort }}">

        @include('admin.partials._search_toggle', ['target' => 'single-page-search-body', 'isSearching' => $isSearching])

        <div id="single-page-search-body" @class(['collapse', 'show' => $isSearching])>
            <div class="d-flex flex-wrap flex-xl-nowrap gap-3 align-items-end pt-3">
                <div style="width: 14rem;">
                    <label for="search-title" class="form-label small">{{ __('タイトル') }}</label>
                    <input id="search-title" type="text" name="title" value="{{ $filters['title'] ?? '' }}" class="form-control form-control-sm">
                </div>

                @include('admin.partials._publication_period_search', ['filters' => $filters])

                <div class="d-flex gap-2 text-nowrap">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('検索') }}</button>
                    <a href="{{ route('admin.single-pages.index', ['sort' => $sort]) }}" class="btn btn-sm btn-outline-secondary">{{ __('クリア') }}</a>
                </div>
            </div>
        </div>
    </form>

    @if ($canReorder && $singlePages->isNotEmpty())
        <form id="single-page-reorder-form" method="POST" action="{{ route('admin.single-pages.reorder') }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="offset" value="{{ $singlePages->firstItem() ? $singlePages->firstItem() - 1 : 0 }}">
        </form>
    @endif

    <div class="card">
        <table class="table table-hover mb-0 align-middle" @if ($canReorder) id="single-page-reorder-rows" @endif>
            <thead>
                <tr>
                    @if ($canReorder)
                        <th></th>
                    @endif
                    @include('admin.partials._sortable_th', ['label' => __('タイトル'), 'field' => 'title', 'defaultDirection' => 'asc'])
                    <th>{{ __('概要') }}</th>
                    @include('admin.partials._sortable_th', ['label' => __('公開開始'), 'field' => 'publication_start', 'defaultDirection' => 'desc'])
                    @include('admin.partials._sortable_th', ['label' => __('公開終了'), 'field' => 'publication_end', 'defaultDirection' => 'desc'])
                    <th>{{ __('Topページへ表示する') }}</th>
                    <th>{{ __('リンクリストへ表示する') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($singlePages as $singlePage)
                    <tr data-role="single-page-row">
                        @if ($canReorder)
                            <td class="single-page-reorder-handle-cell">
                                <span class="single-page-detail-handle" data-role="drag-handle" title="{{ __('ドラッグして並び替え') }}">
                                    <i class="bi bi-grip-vertical"></i>
                                </span>
                                <input type="hidden" form="single-page-reorder-form" name="order[]" value="{{ $singlePage->id }}">
                            </td>
                        @endif
                        <td>{{ $singlePage->title }}</td>
                        <td>{{ $singlePage->short_sentences }}</td>
                        <td>{{ $singlePage->publication_start_datetime?->format('Y/m/d H:i') }}</td>
                        <td>{{ $singlePage->publication_end_datetime?->format('Y/m/d H:i') ?? __('未設定') }}</td>
                        <td>{{ $singlePage->top_page_view ? __('表示する') : __('表示しない') }}</td>
                        <td>{{ $singlePage->link_list_view ? __('表示する') : __('表示しない') }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.single-pages.edit', $singlePage) }}" class="btn btn-sm btn-outline-secondary">{{ __('編集') }}</a>

                            <form
                                method="POST"
                                action="{{ route('admin.single-pages.destroy', $singlePage) }}"
                                class="d-inline"
                                onsubmit="return confirm('{{ __('削除してよろしいですか?') }}');"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('削除') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canReorder ? 8 : 7 }}" class="text-center text-muted py-4">{{ __('該当する固定ページがありません。') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $singlePages->links() }}
    </div>
@endsection
