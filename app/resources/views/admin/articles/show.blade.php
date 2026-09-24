@extends('layouts.admin')

@section('title', __('記事詳細'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('記事詳細') }}</h1>
        <a href="{{ route('admin.articles.edit', $article) }}" class="link-primary">{{ __('編集する') }}</a>
    </div>

    <div class="card" style="max-width: 48rem;">
        <div class="p-3">
            <img
                src="{{ $article->thumbnail_url }}"
                alt="{{ $article->title }}"
                class="img-thumbnail mb-3"
                style="width: 240px; height: 240px; object-fit: cover;"
            >

            <dl class="row mb-0">
                <dt class="col-3 text-muted fw-normal">{{ __('タイトル') }}</dt>
                <dd class="col-9">{{ $article->title }}</dd>

                <dt class="col-3 text-muted fw-normal">{{ __('投稿者') }}</dt>
                <dd class="col-9">{{ $article->user_name }}</dd>

                <dt class="col-3 text-muted fw-normal">{{ __('ステータス') }}</dt>
                <dd class="col-9">{{ $article->approval->label() }}</dd>

                <dt class="col-3 text-muted fw-normal">{{ __('公開開始') }}</dt>
                <dd class="col-9">{{ $article->publication_start_datetime->format('Y/m/d H:i') }}</dd>

                <dt class="col-3 text-muted fw-normal">{{ __('公開終了') }}</dt>
                <dd class="col-9">{{ optional($article->publication_end_datetime)->format('Y/m/d H:i') ?? __('未設定') }}</dd>

                <dt class="col-3 text-muted fw-normal">{{ __('タグ') }}</dt>
                <dd class="col-9">
                    @forelse ($article->tags as $tag)
                        <span class="badge text-bg-secondary">{{ $tag->tag_name }}</span>
                    @empty
                        <span class="text-muted">{{ __('タグはありません。') }}</span>
                    @endforelse
                </dd>

                <dt class="col-3 text-muted fw-normal">{{ __('更新日時') }}</dt>
                <dd class="col-9 mb-0">{{ $article->updated_at->format('Y/m/d H:i') }}</dd>
            </dl>

            <hr>

            <div>{!! $article->content !!}</div>
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.articles.index') }}" class="text-secondary">{{ __('一覧へ戻る') }}</a>
    </div>
@endsection
