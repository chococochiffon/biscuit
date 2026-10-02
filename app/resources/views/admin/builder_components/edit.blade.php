@extends('layouts.admin')

@section('title', __('コンポーネントの編集'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 32rem;">{{ __('コンポーネントの編集') }}</h1>

    <form method="POST" action="{{ route('admin.builder-components.update', $builderComponent) }}" class="mx-auto" style="max-width: 32rem;">
        @csrf
        @method('PUT')

        @include('admin.builder_components._form')

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('更新する') }}
            </button>
            <a href="{{ route('admin.builder-components.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
            <a href="{{ route('admin.builder.components', $builderComponent) }}" class="ms-auto btn btn-outline-secondary">
                <i class="bi bi-grid-1x2"></i> {{ __('ページビルダーで編集') }}
            </a>
        </div>
    </form>
@endsection
