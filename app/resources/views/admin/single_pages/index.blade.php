@extends('layouts.admin')

@section('title', __('固定ページ一覧'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('固定ページ一覧') }}</h1>

        <a href="{{ route('admin.single-pages.create') }}" class="btn btn-primary">
            {{ __('新規登録') }}
        </a>
    </div>

    @if ($singlePages->isNotEmpty())
        <form id="single-page-reorder-form" method="POST" action="{{ route('admin.single-pages.reorder') }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="offset" value="{{ $singlePages->firstItem() ? $singlePages->firstItem() - 1 : 0 }}">
        </form>
    @endif

    <div class="card">
        <table class="table table-hover mb-0 align-middle" id="single-page-reorder-rows">
            <thead>
                <tr>
                    <th></th>
                    <th>{{ __('タイトル') }}</th>
                    <th>{{ __('概要') }}</th>
                    <th>{{ __('Topページへ表示する') }}</th>
                    <th>{{ __('リンクリストへ表示する') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($singlePages as $singlePage)
                    <tr data-role="single-page-row">
                        <td class="single-page-reorder-handle-cell">
                            <span class="single-page-detail-handle" data-role="drag-handle" title="{{ __('ドラッグして並び替え') }}">
                                <i class="bi bi-grip-vertical"></i>
                            </span>
                            <input type="hidden" form="single-page-reorder-form" name="order[]" value="{{ $singlePage->id }}">
                        </td>
                        <td>
                            <a href="{{ route('admin.single-pages.show', $singlePage) }}">
                                {{ $singlePage->title }}
                            </a>
                        </td>
                        <td>{{ $singlePage->short_sentences }}</td>
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
                        <td colspan="6" class="text-center text-muted py-4">{{ __('固定ページが登録されていません。') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($singlePages->isNotEmpty())
        <div class="mt-3">
            <button type="submit" form="single-page-reorder-form" class="btn btn-outline-secondary btn-sm">
                {{ __('並び替えを保存') }}
            </button>
        </div>
    @endif

    <div class="mt-3">
        {{ $singlePages->links() }}
    </div>
@endsection
