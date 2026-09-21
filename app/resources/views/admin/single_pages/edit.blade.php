@extends('layouts.admin')

@section('title', __('固定ページの編集'))

@section('content')
    <h1 class="h5 mb-4">{{ __('固定ページの編集') }}</h1>

    <form
        method="POST"
        action="{{ route('admin.single-pages.update', $singlePage) }}"
        enctype="multipart/form-data"
        style="max-width: 48rem;"
    >
        @csrf
        @method('PUT')

        @include('admin.single_pages._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('保存する') }}
            </button>
            <a href="{{ route('admin.single-pages.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
