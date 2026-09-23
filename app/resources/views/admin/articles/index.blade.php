@extends('layouts.admin')

@section('title', __('記事一覧'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('記事一覧') }}</h1>

        <a href="{{ route('admin.articles.create') }}" class="btn btn-primary">
            {{ __('新規登録') }}
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body d-flex align-items-center gap-2 flex-wrap">
            <span class="small text-muted">{{ __('選択した記事の公開設定を一括変更') }}:</span>
            <select id="bulk-approval-select" class="form-select form-select-sm" style="width: auto;">
                @foreach (\App\Enums\ArticleApprovalStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
            <button
                type="button"
                id="bulk-approval-submit"
                class="btn btn-sm btn-outline-primary"
                data-action-url="{{ route('admin.articles.bulk-approval') }}"
            >
                {{ __('一括変更') }}
            </button>
        </div>
    </div>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width: 2.5rem;">
                        <input type="checkbox" class="form-check-input" data-role="select-all-articles" aria-label="{{ __('すべて選択') }}">
                    </th>
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
                            <input
                                type="checkbox"
                                class="form-check-input"
                                value="{{ $article->id }}"
                                data-role="article-checkbox"
                                aria-label="{{ __('選択') }}"
                            >
                        </td>
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
                        <td>
                            <select
                                class="form-select form-select-sm"
                                style="width: auto;"
                                data-role="approval-inline-select"
                                data-update-url="{{ route('admin.articles.approval', $article) }}"
                                aria-label="{{ __('公開ステータス') }}"
                            >
                                @foreach (\App\Enums\ArticleApprovalStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected($article->approval === $status)>
                                        {{ $status->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
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
                        <td colspan="7" class="text-center text-muted py-4">{{ __('記事が登録されていません。') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $articles->links() }}
    </div>
@endsection
