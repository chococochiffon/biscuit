@extends('layouts.admin')

@section('title', __('サイト設定の編集'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 80rem;">{{ __('サイト設定の編集') }}</h1>

    <form
        method="POST"
        action="{{ route('admin.site-settings.update', $siteSetting) }}"
        enctype="multipart/form-data"
        class="mx-auto"
        style="max-width: 80rem;"
    >
        @csrf
        @method('PUT')

        @include('admin.site_settings._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('更新する') }}
            </button>
            <a href="{{ route('admin.site-settings.show', $siteSetting) }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
