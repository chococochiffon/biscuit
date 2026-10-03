@extends('installer.layout')

@section('title', __('デザイン'))

@section('content')
    <h2 class="h5">{{ __('デザイン') }}</h2>
    <p class="text-secondary small">{{ __('サイトのトップの見た目を決めます。どちらを選んでも、インストールのあとに管理画面のビルダーとレイアウト管理で直せます。') }}</p>

    <form method="POST" action="{{ route('installer.design.store') }}" id="design-form">
        @csrf

        <div class="form-check border rounded p-3 ps-5 mb-2">
            <input id="design-default" class="form-check-input" type="radio" name="design" value="default" @checked($selected === 'default')>
            <label for="design-default" class="form-check-label">
                <span class="fw-semibold">{{ __('デフォルトのデザインを使う') }}</span>
                <span class="d-block small text-secondary">
                    {{ __('ヘッダー(サイト名・メニュー)、トップの大きな見出し(サイト名・説明)と新着記事、フッター(コピーライト)を用意します。インストールのあとに、管理画面のビルダーとレイアウト管理で自由に直せます。') }}
                </span>
            </label>
        </div>
        <div class="form-check border rounded p-3 ps-5 mb-3">
            <input id="design-builder" class="form-check-input" type="radio" name="design" value="builder" @checked($selected === 'builder') @disabled(! $canUseBuilder)>
            <label for="design-builder" class="form-check-label w-100">
                <span class="fw-semibold">{{ __('ビルダーで作る') }}</span>
                <span class="d-block small text-secondary">
                    {{ __('ブロック(セクション・見出し・テキスト・画像・ボタンなど)を組み立てて、トップを作ります。何もない状態か、テンプレートから始められます。ヘッダー・フッターはデフォルトと同じものを用意します。') }}
                </span>
            </label>
        </div>
    </form>

    @if ($canUseBuilder)
        <div class="border rounded p-3 mb-4" data-design-builder>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="small">
                    @if ($hasDraft)
                        <i class="bi bi-check-circle-fill text-success"></i> {{ __('ビルダーで作った下書きがあります。') }}
                    @else
                        <span class="text-secondary">{{ __('まだビルダーで作っていません。') }}</span>
                    @endif
                </div>
                <a href="{{ route('installer.design.builder') }}" class="btn btn-outline-primary btn-sm" data-open-builder>
                    <i class="bi bi-pencil-square"></i> {{ $hasDraft ? __('ビルダーで編集する') : __('ビルダーを開く') }}
                </a>
            </div>
            @if ($previewUrl)
                {{-- 公開側(chococo)の下書きのプレビュー。公開側を起動できていないときは表示されない --}}
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold">{{ __('プレビュー') }}</span>
                        <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="small">{{ __('新しいタブで開く') }} <i class="bi bi-box-arrow-up-right"></i></a>
                    </div>
                    <iframe src="{{ $previewUrl }}" title="{{ __('プレビュー') }}" class="w-100 border rounded" style="height: 420px;" loading="lazy" data-design-preview></iframe>
                </div>
            @endif
        </div>
    @else
        {{-- 最初の管理者を作ったブラウザでないときは、その管理者のメールアドレスとパスワードで解除する --}}
        <form method="POST" action="{{ route('installer.design.unlock') }}" class="border rounded p-3 mb-4" data-design-unlock>
            @csrf
            <p class="small text-secondary">{{ __('ビルダーは、管理者を作ったブラウザでだけ使えます。別のブラウザで続けるときは、作った管理者のメールアドレスとパスワードを入れてください。') }}</p>
            <div class="row g-2 align-items-end">
                <div class="col-sm-5">
                    <label for="unlock-email" class="form-label small">{{ __('メールアドレス') }}</label>
                    <input id="unlock-email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" class="form-control form-control-sm">
                </div>
                <div class="col-sm-5">
                    <label for="unlock-password" class="form-label small">{{ __('パスワード') }}</label>
                    <input id="unlock-password" type="password" name="password" required autocomplete="current-password" class="form-control form-control-sm">
                </div>
                <div class="col-sm-2">
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100">{{ __('解除') }}</button>
                </div>
            </div>
        </form>
    @endif

    <div class="d-flex flex-wrap justify-content-between gap-2">
        {{-- 「あとで設定する」はデフォルトのデザインを使う(設計書 20 章) --}}
        <button type="submit" form="design-form" name="design" value="default" class="btn btn-link px-0" formnovalidate>{{ __('あとで設定する(デフォルトのデザインを使う)') }}</button>
        <button type="submit" form="design-form" class="btn btn-primary px-4">{{ __('このデザインで次へ') }}</button>
    </div>
@endsection
