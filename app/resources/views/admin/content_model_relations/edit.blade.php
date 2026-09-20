@extends('layouts.admin')

@section('title', __('データ種別紐付けの編集'))

@section('content')
    <h1 class="h5 mb-4">{{ __('データ種別紐付けの編集') }}</h1>

    <form
        method="POST"
        action="{{ route('admin.content-model-relations.update', $contentModelRelation) }}"
        style="max-width: 32rem;"
    >
        @csrf
        @method('PUT')

        @include('admin.content_model_relations._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('更新する') }}
            </button>
            <a href="{{ route('admin.content-model-relations.show', $contentModelRelation) }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
