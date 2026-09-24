@extends('layouts.admin')

@section('title', __('記事の編集'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 80rem;">{{ __('記事の編集') }}</h1>

    <form
        method="POST"
        action="{{ route('admin.articles.update', $article) }}"
        enctype="multipart/form-data"
        class="mx-auto"
        style="max-width: 80rem;"
    >
        @csrf
        @method('PUT')

        @include('admin.articles._form', ['submitLabel' => __('更新する')])
    </form>
@endsection
