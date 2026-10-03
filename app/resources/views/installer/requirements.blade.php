@extends('installer.layout')

@section('title', __('環境の確認'))

@section('content')
    <div class="text-center mb-4">
        <h2 class="h5">{{ __('インストールを開始します') }}</h2>
        <p class="text-secondary small mb-0">{{ __('Biscuit を動かせる環境かを確かめます。すべて満たすと、次へ進めます。') }}</p>
    </div>

    @if ($resumeStep)
        {{-- 前回の続き(どれかの段を終えている)。終えた段の内容は残っている --}}
        <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2" role="status" data-installer-resume>
            <div class="small">
                <div class="fw-semibold">{{ __('前回のインストールの続きから再開できます。') }}</div>
                <div>{{ __('次の段: :step', ['step' => $resumeStep->label()]) }}</div>
            </div>
            <a href="{{ route($resumeStep->routeName()) }}" class="btn btn-primary btn-sm">{{ __('続きから再開する') }}</a>
        </div>
    @endif

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
