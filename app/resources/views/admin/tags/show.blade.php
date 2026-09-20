@extends('layouts.admin')

@section('title', __('タグ詳細'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('タグ詳細') }}</h1>
        <a href="{{ route('admin.tags.edit', $tag) }}" class="link-primary">{{ __('編集する') }}</a>
    </div>

    <div class="card" style="max-width: 28rem;">
        <dl class="row mb-0 p-3">
            <dt class="col-4 text-muted fw-normal">{{ __('タグ名') }}</dt>
            <dd class="col-8 mb-0">{{ $tag->tag_name }}</dd>
        </dl>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.tags.index') }}" class="text-secondary">{{ __('一覧へ戻る') }}</a>
    </div>
@endsection
