@extends('layouts.admin')

@section('title', __('記事一覧'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('記事一覧') }}</h1>

        <a href="{{ route('admin.articles.create') }}" class="btn btn-primary">
            {{ __('新規登録') }}
        </a>
    </div>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('サムネイル') }}</th>
                    <th>{{ __('タイトル') }}</th>
                    <th>{{ __('投稿者') }}</th>
                    <th>{{ __('更新日時') }}</th>
                    <th>{{ __('ステータス') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($articles as $article)
                    <tr>
                        <td>
                            <img
                                src="{{ $article->thumbnail_url }}"
                                alt="{{ $article->title }}"
                                class="img-thumbnail"
                                style="width: 64px; height: 64px; object-fit: cover;"
                            >
                        </td>
                        <td>
                            <a href="{{ route('admin.articles.show', $article) }}">
                                {{ $article->title }}
                            </a>
                        </td>
                        <td>{{ $article->user_name }}</td>
                        <td>{{ $article->updated_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $article->approval->label() }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.articles.edit', $article) }}" class="btn btn-sm btn-outline-secondary">{{ __('編集') }}</a>

                            <form
                                method="POST"
                                action="{{ route('admin.articles.destroy', $article) }}"
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
                        <td colspan="6" class="text-center text-muted py-4">{{ __('記事が登録されていません。') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $articles->links() }}
    </div>
@endsection
