@extends('layouts.admin')

@section('title', __('管理者詳細'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('管理者詳細') }}</h1>
        <a href="{{ route('admin.edit', $administrator) }}" class="link-primary">{{ __('編集する') }}</a>
    </div>

    <div class="card" style="max-width: 28rem;">
        <dl class="row mb-0 p-3">
            <dt class="col-4 text-muted fw-normal">{{ __('名前') }}</dt>
            <dd class="col-8">{{ $administrator->name }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('メールアドレス') }}</dt>
            <dd class="col-8">{{ $administrator->email }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('権限') }}</dt>
            <dd class="col-8">{{ $administrator->role->label() }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('最終ログイン') }}</dt>
            <dd class="col-8 mb-0">{{ $administrator->last_login_at?->format('Y-m-d H:i') ?? __('未ログイン') }}</dd>
        </dl>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.index') }}" class="text-secondary">{{ __('一覧へ戻る') }}</a>
    </div>
@endsection
