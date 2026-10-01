@extends('layouts.admin')

@section('title', __('グローバルコンポーネントの登録'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 32rem;">{{ __('グローバルコンポーネントの登録') }}</h1>

    <form method="POST" action="{{ route('admin.builder-components.store') }}" class="mx-auto" style="max-width: 32rem;">
        @csrf

        @include('admin.builder_components._form')

        <p class="small text-secondary">{{ __('登録すると、ページビルダーが開きます。中身はページビルダーで組み立てます。') }}</p>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('登録する') }}
            </button>
            <a href="{{ route('admin.builder-components.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
