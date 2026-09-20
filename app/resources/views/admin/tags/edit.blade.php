@extends('layouts.admin')

@section('title', __('タグの編集'))

@section('content')
    <h1 class="h5 mb-4">{{ __('タグの編集') }}</h1>

    <form method="POST" action="{{ route('admin.tags.update', $tag) }}" style="max-width: 28rem;">
        @csrf
        @method('PUT')

        @include('admin.tags._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('更新する') }}
            </button>
            <a href="{{ route('admin.tags.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
