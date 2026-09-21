@extends('layouts.admin')

@section('title', __('ユーザーの新規登録'))

@section('content')
    <h1 class="h5 mb-4">{{ __('ユーザーの新規登録') }}</h1>

    <form method="POST" action="{{ route('admin.users.store') }}" enctype="multipart/form-data" style="max-width: 32rem;">
        @csrf

        @include('admin.users._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('登録する') }}
            </button>
            <a href="{{ route('admin.users.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
