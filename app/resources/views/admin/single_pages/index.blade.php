@extends('layouts.admin')

@section('title', __('固定ページ一覧'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('固定ページ一覧') }}</h1>

        <a href="{{ route('admin.single-pages.create') }}" class="btn btn-primary">
            {{ __('新規登録') }}
        </a>
    </div>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('タイトル') }}</th>
                    <th>{{ __('概要') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($singlePages as $singlePage)
                    <tr>
                        <td>
                            <a href="{{ route('admin.single-pages.show', $singlePage) }}">
                                {{ $singlePage->title }}
                            </a>
                        </td>
                        <td>{{ $singlePage->short_sentences }}</td>
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
                        <td colspan="3" class="text-center text-muted py-4">{{ __('固定ページが登録されていません。') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $singlePages->links() }}
    </div>
@endsection
