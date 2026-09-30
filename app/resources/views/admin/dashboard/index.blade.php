@extends('layouts.admin')

@section('title', __('ダッシュボード'))

@section('content')
    <div class="mx-auto" style="max-width: 80rem;">
        <h1 class="h5 mb-4">{{ __('ダッシュボード') }}</h1>

        <div class="mb-3 d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0">{{ __('アクセス') }}</h2>
            <a href="{{ route('admin.page-views.index') }}" class="link-primary">{{ __('アクセス解析を見る') }}</a>
        </div>

        @include('admin.page_views._summary')
    </div>
@endsection
