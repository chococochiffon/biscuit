@extends('layouts.admin')

@section('title', __('ギャラリー画像の新規登録'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 40rem;">{{ __('ギャラリー画像の新規登録') }}</h1>

    <form method="POST" action="{{ route('admin.gallery-images.store') }}" enctype="multipart/form-data" class="mx-auto" style="max-width: 40rem;">
        @csrf

        @include('admin.gallery_images._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('登録する') }}
            </button>
            <a href="{{ route('admin.gallery-images.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
