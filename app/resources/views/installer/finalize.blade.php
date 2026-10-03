@extends('installer.layout')

@section('title', __('完了'))

@section('content')
    <h2 class="h5">{{ __('インストールの確認') }}</h2>
    <p class="text-secondary small">{{ __('内容を確かめて、インストールを完了します。完了したあとは、このインストーラーは使えなくなります。') }}</p>

    <ul class="list-group mb-4" data-finalize-checks>
        @foreach ($checks as $check)
            <li class="list-group-item d-flex align-items-center gap-2 small">
                <i class="bi {{ $check['ok'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }}"></i>
                <span class="flex-grow-1">{{ $check['label'] }}</span>
                @if ($check['detail'])
                    <span class="text-secondary">{{ $check['detail'] }}</span>
                @endif
            </li>
        @endforeach
    </ul>

    <form method="POST" action="{{ route('installer.finalize.store') }}" class="text-center">
        @csrf
        <button type="submit" class="btn btn-primary px-5" @disabled(! $passes)>{{ __('インストールを完了する') }}</button>
    </form>
@endsection
