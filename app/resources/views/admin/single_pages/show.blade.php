@extends('layouts.admin')

@section('title', __('固定ページ詳細'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('固定ページ詳細') }}</h1>
        <a href="{{ route('admin.single-pages.edit', $singlePage) }}" class="link-primary">{{ __('編集する') }}</a>
    </div>

    <div class="card" style="max-width: 48rem;">
        <dl class="row mb-0 p-3">
            <dt class="col-4 text-muted fw-normal">{{ __('タイトル') }}</dt>
            <dd class="col-8">{{ $singlePage->title }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('概要') }}</dt>
            <dd class="col-8">{{ $singlePage->short_sentences }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('タクソノミー') }}</dt>
            <dd class="col-8">{{ $singlePage->taxonomy ?? __('未設定') }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('URI') }}</dt>
            <dd class="col-8">{{ $singlePage->uri ?? __('未設定') }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('Topページへ表示する') }}</dt>
            <dd class="col-8">{{ $singlePage->top_page_view ? __('表示する') : __('表示しない') }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('リンクリストへ表示する') }}</dt>
            <dd class="col-8">{{ $singlePage->link_list_view ? __('表示する') : __('表示しない') }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('公開開始日時') }}</dt>
            <dd class="col-8">{{ $singlePage->publication_start_datetime->format('Y-m-d H:i') }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('公開終了日時') }}</dt>
            <dd class="col-8">{{ optional($singlePage->publication_end_datetime)->format('Y-m-d H:i') ?? __('未設定') }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('ヘッダー画像') }}</dt>
            <dd class="col-8 mb-0">
                @if ($singlePage->header_image)
                    <img
                        src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($singlePage->header_image) }}"
                        alt="{{ __('ヘッダー画像') }}"
                        class="img-thumbnail"
                        style="width: 240px; height: 160px; object-fit: cover;"
                    >
                @else
                    {{ __('未設定') }}
                @endif
            </dd>
        </dl>
    </div>

    <h2 class="h6 mt-4 mb-3">{{ __('詳細') }}</h2>

    @forelse ($singlePage->details as $detail)
        <div class="card mb-3" style="max-width: 48rem;">
            <div class="card-body">
                <h3 class="h6">{{ $detail->sub_title }}</h3>
                <div>{!! $detail->contents !!}</div>
            </div>
        </div>
    @empty
        <p class="text-muted">{{ __('詳細が登録されていません。') }}</p>
    @endforelse

    <div class="mt-4">
        <a href="{{ route('admin.single-pages.index') }}" class="text-secondary">{{ __('一覧へ戻る') }}</a>
    </div>
@endsection
