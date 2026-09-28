@extends('layouts.admin')

@section('title', __(':labelの編集', ['label' => $customPageType->label]))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 80rem;">{{ __(':labelの編集', ['label' => $customPageType->label]) }}</h1>

    <form method="POST" action="{{ route('admin.custom-pages.entries.update', [$customPageType, $entry->id]) }}" class="mx-auto" style="max-width: 80rem;">
        @csrf
        @method('PUT')

        @include('admin.custom_pages._form', ['submitLabel' => __('更新する')])
    </form>
@endsection
