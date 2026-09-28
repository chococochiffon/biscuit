@extends('layouts.admin')

@section('title', __('ギャラリー画像の編集'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 40rem;">{{ __('ギャラリー画像の編集') }}</h1>

    <form method="POST" action="{{ route('admin.gallery-images.update', $galleryImage) }}" enctype="multipart/form-data" class="mx-auto" style="max-width: 40rem;">
        @csrf
        @method('PUT')

        @include('admin.gallery_images._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('更新する') }}
            </button>
            <a href="{{ route('admin.gallery-images.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
