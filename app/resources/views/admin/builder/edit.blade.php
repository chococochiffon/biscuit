@extends('layouts.builder')

@section('title', __('ページビルダー') . ': ' . $title)

@section('content')
    {{-- Vue のエディタ(resources/js/builder.ts)をここに描く。読み込めない場合だけ下の文言が残る --}}
    <div id="page-builder" data-config="{{ json_encode($config) }}">
        <div class="p-4 text-secondary">{{ __('ページビルダーを読み込んでいます...') }}</div>
    </div>
@endsection
