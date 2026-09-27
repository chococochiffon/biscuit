@extends('layouts.admin')

@section('title', __('データ種別紐付け詳細'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between mx-auto" style="max-width: 32rem;">
        <h1 class="h5 mb-0">{{ __('データ種別紐付け詳細') }}</h1>
        <a href="{{ route('admin.content-model-relations.edit', $contentModelRelation) }}" class="link-primary">{{ __('編集する') }}</a>
    </div>

    <div class="card mx-auto" style="max-width: 32rem;">
        <dl class="row mb-0 p-3 detail-list">
            <x-admin.detail-row :label="__('コンテンツ種別')">{{ $contentModelRelation->content_type->label() }}</x-admin.detail-row>

            <x-admin.detail-row :label="__('モデル名')">{{ $contentModelRelation->model_name }}</x-admin.detail-row>

            <x-admin.detail-row :label="__('テーブル名')">{{ $contentModelRelation->table_name }}</x-admin.detail-row>
        </dl>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.content-model-relations.index') }}" class="text-secondary">{{ __('一覧へ戻る') }}</a>
    </div>
@endsection
