@extends('layouts.admin')

@section('title', __('サイト設定の登録'))

@section('content')
    <h1 class="h5 mb-4">{{ __('サイト設定の登録') }}</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.site-settings.store') }}"
        enctype="multipart/form-data"
        style="max-width: 40rem;"
    >
        @csrf

        <div class="mb-3">
            <label for="site_title" class="form-label">{{ __('サイトタイトル') }}</label>
            <input
                id="site_title"
                type="text"
                name="site_title"
                value="{{ old('site_title') }}"
                required
                class="form-control"
            >
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">{{ __('説明') }}</label>
            <textarea id="description" name="description" rows="4" class="form-control">{{ old('description') }}</textarea>
        </div>

        <div class="mb-3">
            <label for="site_icon" class="form-label">{{ __('サイトアイコン') }}</label>
            <input id="site_icon" type="file" name="site_icon" accept="image/*" class="form-control">
        </div>

        <div class="mb-3">
            <label for="site_image" class="form-label">{{ __('サイト画像') }}</label>
            <input id="site_image" type="file" name="site_image" accept="image/*" class="form-control">
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('登録する') }}
            </button>
            <a href="{{ route('admin.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
