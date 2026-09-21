@extends('layouts.admin')

@section('title', __('サイト設定の登録'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('サイト設定の登録') }}</h1>
        <a href="{{ route('admin.content-model-relations.index') }}" class="link-secondary">{{ __('データ種別紐付け一覧') }}</a>
    </div>

    <form
        method="POST"
        action="{{ route('admin.site-settings.store') }}"
        enctype="multipart/form-data"
        style="max-width: 56rem;"
    >
        @csrf

        @include('admin.site_settings._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('登録する') }}
            </button>
            <a href="{{ route('admin.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
