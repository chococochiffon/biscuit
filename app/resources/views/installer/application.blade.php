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

    @if ($running)
        <div class="alert alert-info d-flex align-items-center gap-2" role="status" data-application-running>
            <div class="spinner-border spinner-border-sm" aria-hidden="true"></div>
            <div>
                {{ $pending ? __('インストーラーの起動の画面(install.sh)が受け取るのを待っています。install.sh を動かしたままにしてください。') : __('セットアップしています...') }}
                @if ($status['stage'] ?? null)
                    <div class="small text-secondary">{{ __('今の処理') }}: {{ $status['stage'] }}</div>
                @endif
            </div>
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
