@extends('installer.layout')

@section('title', __('アプリケーション'))

@php($running = $pending || ($status['status'] ?? null) === 'running')

@if ($running || ($status['status'] ?? null) === 'succeeded')
    @push('head')
        {{-- 進み具合を数秒ごとに読み込み直す(JavaScript を使わない) --}}
        <meta http-equiv="refresh" content="3">
    @endpush
@endif

@section('content')
    <h2 class="h5">{{ __('アプリケーション') }}</h2>
    <p class="text-secondary small">
        {{ __('データベースと公開側のサイトを起動し、データベースを作ってテーブルを用意します。初回は数分かかることがあります。') }}
    </p>

    @if ($running || in_array($status['status'] ?? null, ['failed', 'succeeded'], true))
        {{-- 処理の一覧と進捗バー(終えた処理の数で進める) --}}
        @php($doneCount = count(array_filter($stages, fn (array $stage) => $stage['state'] === 'done')))
        <div class="progress mb-3" role="progressbar" aria-label="{{ __('セットアップの進み具合') }}" aria-valuenow="{{ $doneCount }}" aria-valuemin="0" aria-valuemax="{{ count($stages) }}" data-application-progress>
            <div class="progress-bar {{ ($status['status'] ?? null) === 'failed' ? 'bg-danger' : '' }} {{ $running ? 'progress-bar-striped progress-bar-animated' : '' }}" style="width: {{ round($doneCount / count($stages) * 100) }}%"></div>
        </div>
        <ul class="list-group mb-3" data-application-stages>
            @foreach ($stages as $stage)
                <li class="list-group-item d-flex align-items-center gap-2 small {{ $stage['state'] === 'waiting' ? 'text-secondary' : '' }}" data-stage="{{ $stage['key'] }}" data-state="{{ $stage['state'] }}">
                    @switch($stage['state'])
                        @case('done')
                            <i class="bi bi-check-circle-fill text-success"></i>
                            @break
                        @case('running')
                            <span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span>
                            @break
                        @case('failed')
                            <i class="bi bi-x-circle-fill text-danger"></i>
                            @break
                        @default
                            <i class="bi bi-circle"></i>
                    @endswitch
                    <span>{{ $stage['label'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($running)
        <div class="alert alert-info small" role="status" data-application-running>
            {{ $pending ? __('インストーラーの起動の画面(install.sh)が受け取るのを待っています。install.sh を動かしたままにしてください。') : (isset($status['message']) ? __($status['message']) : __('セットアップしています...')) }}
        </div>
    @elseif (($status['status'] ?? null) === 'failed')
        <div class="alert alert-danger" role="alert" data-application-failed>
            <div class="fw-semibold">{{ __('セットアップに失敗しました。') }}@if ($status['stage']) ({{ $status['stage'] }})@endif</div>
            @if ($status['message'])
                <div class="small mt-1" style="white-space: pre-line;">{{ $status['message'] }}</div>
            @endif
            <div class="small mt-1">{{ __('詳しくは storage/logs/installer.log・install.log と、install.sh の画面を確認してください。') }}</div>
        </div>
        <form method="POST" action="{{ route('installer.application.start') }}" class="d-flex gap-2">
            @csrf
            <button type="submit" class="btn btn-primary">{{ __('再試行') }}</button>
            <a href="{{ route('installer.database') }}" class="btn btn-outline-secondary">{{ __('データベースの設定に戻る') }}</a>
        </form>
    @elseif (($status['status'] ?? null) === 'succeeded')
        <div class="alert alert-success" role="status">{{ __('セットアップを終えました。次の段へ進みます...') }}</div>
    @else
        <form method="POST" action="{{ route('installer.application.start') }}" class="text-center">
            @csrf
            <button type="submit" class="btn btn-primary px-5" data-application-start>{{ __('セットアップを始める') }}</button>
        </form>
    @endif
@endsection
