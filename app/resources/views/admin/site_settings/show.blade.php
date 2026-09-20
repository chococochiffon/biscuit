@extends('layouts.admin')

@section('title', __('サイト設定詳細'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('サイト設定詳細') }}</h1>
        <a href="{{ route('admin.site-settings.edit', $siteSetting) }}" class="link-primary">{{ __('編集する') }}</a>
    </div>

    <div class="card" style="max-width: 40rem;">
        <dl class="row mb-0 p-3">
            <dt class="col-4 text-muted fw-normal">{{ __('サイトタイトル') }}</dt>
            <dd class="col-8">{{ $siteSetting->site_title }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('説明') }}</dt>
            <dd class="col-8">{{ $siteSetting->description }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('サイトアイコン') }}</dt>
            <dd class="col-8">
                @if ($siteSetting->site_icon)
                    <img
                        src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($siteSetting->site_icon) }}"
                        alt="{{ __('サイトアイコン') }}"
                        class="img-thumbnail"
                        style="width: 96px; height: 96px; object-fit: cover;"
                    >
                @else
                    <span class="text-muted">{{ __('未設定') }}</span>
                @endif
            </dd>

            <dt class="col-4 text-muted fw-normal">{{ __('サイト画像') }}</dt>
            <dd class="col-8 mb-0">
                @if ($siteSetting->site_image)
                    <img
                        src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($siteSetting->site_image) }}"
                        alt="{{ __('サイト画像') }}"
                        class="img-thumbnail"
                        style="width: 240px; height: 160px; object-fit: cover;"
                    >
                @else
                    <span class="text-muted">{{ __('未設定') }}</span>
                @endif
            </dd>
        </dl>
    </div>
@endsection
