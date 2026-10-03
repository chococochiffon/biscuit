@extends('installer.layout')

@section('title', __('環境の確認'))

@section('content')
    <div class="text-center mb-4">
        <h2 class="h5">{{ __('インストールを開始します') }}</h2>
        <p class="text-secondary small mb-0">{{ __('Biscuit を動かせる環境かを確かめます。すべて満たすと、次へ進めます。') }}</p>
    </div>

    <ul class="list-group mb-4" data-requirements>
        @foreach ($results as $result)
            <li class="list-group-item d-flex align-items-start gap-2 small">
                <i class="bi {{ $result['ok'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }} mt-1"></i>
                <div>
                    <div>{{ $result['label'] }}</div>
                    @if ($result['message'])
                        <div class="text-danger">{{ $result['message'] }}</div>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    <form method="POST" action="{{ route('installer.start') }}" class="text-center">
        @csrf
        <button type="submit" class="btn btn-primary px-5" @disabled(! $passes)>{{ __('開始する') }}</button>
        @unless ($passes)
            <p class="small text-danger mt-2 mb-0">{{ __('満たしていない項目を直してから、画面を読み込み直してください。') }}</p>
        @endunless
    </form>
@endsection
