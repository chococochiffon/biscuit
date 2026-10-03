@extends('installer.layout')

@section('title', __('完了'))

@section('content')
    <h2 class="h5">{{ __('インストールの確認') }}</h2>
    <p class="text-secondary small">{{ __('内容を確かめて、インストールを完了します。完了したあとは、このインストーラーは使えなくなります。') }}</p>

    <h3 class="h6 mt-4">{{ __('必須') }}</h3>
    <p class="small text-secondary mb-2">{{ __('すべて満たすと、インストールを完了できます。') }}</p>
    @include('installer._checks', ['checks' => $required, 'failIcon' => 'bi-x-circle-fill text-danger', 'role' => 'required'])

    <h3 class="h6 mt-4">{{ __('推奨') }}</h3>
    <p class="small text-secondary mb-2">{{ __('満たしていなくても完了できます。本番で公開する前に確かめてください。') }}</p>
    @include('installer._checks', ['checks' => $recommended, 'failIcon' => 'bi-exclamation-triangle-fill text-warning', 'role' => 'recommended'])

    <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
        <a href="{{ route('installer.finalize') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-clockwise"></i> {{ __('確かめ直す') }}
        </a>
        <form method="POST" action="{{ route('installer.finalize.store') }}">
            @csrf
            <button type="submit" class="btn btn-primary px-5" @disabled(! $passes)>{{ __('インストールを完了する') }}</button>
        </form>
    </div>
@endsection
