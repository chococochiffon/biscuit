@extends('layouts.admin')

@section('title', __('管理者詳細'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between mx-auto" style="max-width: 28rem;">
        <h1 class="h5 mb-0">{{ __('管理者詳細') }}</h1>
        <a href="{{ route('admin.edit', $administrator) }}" class="link-primary">{{ __('編集する') }}</a>
    </div>

    <div class="card mx-auto" style="max-width: 28rem;">
        <dl class="row mb-0 p-3 detail-list">
            <x-admin.detail-row :label="__('名前')">{{ $administrator->name }}</x-admin.detail-row>

            <x-admin.detail-row :label="__('メールアドレス')">{{ $administrator->email }}</x-admin.detail-row>

            <x-admin.detail-row :label="__('権限')">{{ $administrator->role->label() }}</x-admin.detail-row>

            <x-admin.detail-row :label="__('最終ログイン')">{{ $administrator->last_login_at?->format('Y/m/d H:i') ?? __('未ログイン') }}</x-admin.detail-row>
        </dl>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.index') }}" class="text-secondary">{{ __('一覧へ戻る') }}</a>
    </div>
@endsection
