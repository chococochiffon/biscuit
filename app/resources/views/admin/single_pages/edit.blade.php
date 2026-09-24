@extends('layouts.admin')

@section('title', __('固定ページの編集'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 80rem;">{{ __('固定ページの編集') }}</h1>

    <form
        method="POST"
        action="{{ route('admin.single-pages.update', $singlePage) }}"
        enctype="multipart/form-data"
        class="mx-auto"
        style="max-width: 80rem;"
    >
        @csrf
        @method('PUT')

        @include('admin.single_pages._form', ['submitLabel' => __('保存する')])
    </form>
@endsection
