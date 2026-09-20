@extends('layouts.admin')

@section('title', __('記事の新規登録'))

@section('content')
    <h1 class="h5 mb-4">{{ __('記事の新規登録') }}</h1>

    <form method="POST" action="{{ route('admin.articles.store') }}" enctype="multipart/form-data" style="max-width: 48rem;">
        @csrf

        @include('admin.articles._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('登録する') }}
            </button>
            <a href="{{ route('admin.articles.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
