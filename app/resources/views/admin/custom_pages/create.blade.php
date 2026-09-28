@extends('layouts.admin')

@section('title', __(':labelの新規登録', ['label' => $customPageType->label]))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 80rem;">{{ __(':labelの新規登録', ['label' => $customPageType->label]) }}</h1>

    <form method="POST" action="{{ route('admin.custom-pages.entries.store', $customPageType) }}" class="mx-auto" style="max-width: 80rem;">
        @csrf

        @include('admin.custom_pages._form', ['submitLabel' => __('登録する')])
    </form>
@endsection
