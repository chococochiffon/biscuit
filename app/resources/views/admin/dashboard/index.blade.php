@extends('layouts.admin')

@section('title', __('ダッシュボード'))

@section('content')
    <div class="mx-auto" style="max-width: 80rem;">
        <h1 class="h5 mb-4">{{ __('ダッシュボード') }}</h1>

        @include('admin.dashboard._system_warnings')

        @include('admin.dashboard._content_counts')

        <div class="row g-3 mb-4">
            <div class="col-lg-8">@include('admin.dashboard._recent_contents')</div>
            <div class="col-lg-4">@include('admin.dashboard._quick_actions')</div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-6">@include('admin.dashboard._scheduled')</div>
            <div class="col-lg-6">@include('admin.dashboard._warnings')</div>
        </div>

        @include('admin.dashboard._recent_operations')

        <div class="row g-3 mb-4">
            <div class="col-lg-6">@include('admin.dashboard._user_status')</div>
            <div class="col-lg-6">@include('admin.dashboard._media_status')</div>
        </div>

        <div class="mb-3 d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0">{{ __('アクセス') }}</h2>
            <a href="{{ route('admin.page-views.index') }}" class="link-primary">{{ __('アクセス解析を見る') }}</a>
        </div>

        @include('admin.page_views._summary')

        @include('admin.dashboard._system_info')
    </div>
@endsection
