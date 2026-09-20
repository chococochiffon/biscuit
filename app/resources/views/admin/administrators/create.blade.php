@extends('layouts.admin')

@section('title', __('管理者の新規登録'))

@section('content')
    <h1 class="h5 mb-4">{{ __('管理者の新規登録') }}</h1>

    <form method="POST" action="{{ route('admin.store') }}" style="max-width: 28rem;">
        @csrf

        @include('admin.administrators._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('登録する') }}
            </button>
            <a href="{{ route('admin.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
