@extends('layouts.admin')

@section('title', __('タグの新規登録'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 28rem;">{{ __('タグの新規登録') }}</h1>

    <form method="POST" action="{{ route('admin.tags.store') }}" class="mx-auto" style="max-width: 28rem;">
        @csrf

        @include('admin.tags._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('登録する') }}
            </button>
            <a href="{{ route('admin.tags.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
