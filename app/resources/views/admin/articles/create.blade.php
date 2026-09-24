@extends('layouts.admin')

@section('title', __('記事の新規登録'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 80rem;">{{ __('記事の新規登録') }}</h1>

    <form method="POST" action="{{ route('admin.articles.store') }}" enctype="multipart/form-data" class="mx-auto" style="max-width: 80rem;">
        @csrf

        @include('admin.articles._form', ['submitLabel' => __('登録する')])
    </form>
@endsection
