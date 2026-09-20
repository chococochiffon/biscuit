@extends('layouts.admin')

@section('title', __('データ種別紐付け詳細'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('データ種別紐付け詳細') }}</h1>
        <a href="{{ route('admin.content-model-relations.edit', $contentModelRelation) }}" class="link-primary">{{ __('編集する') }}</a>
    </div>

    <div class="card" style="max-width: 32rem;">
        <dl class="row mb-0 p-3">
            <dt class="col-4 text-muted fw-normal">{{ __('コンテンツ種別') }}</dt>
            <dd class="col-8">{{ $contentModelRelation->content_type->label() }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('モデル名') }}</dt>
            <dd class="col-8">{{ $contentModelRelation->model_name }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('テーブル名') }}</dt>
            <dd class="col-8 mb-0">{{ $contentModelRelation->table_name }}</dd>
        </dl>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.content-model-relations.index') }}" class="text-secondary">{{ __('一覧へ戻る') }}</a>
    </div>
@endsection
