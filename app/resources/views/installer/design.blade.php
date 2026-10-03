@extends('installer.layout')

@section('title', __('デザイン'))

@section('content')
    <h2 class="h5">{{ __('デザイン') }}</h2>
    <p class="text-secondary small">{{ __('サイトの見た目を決めます。') }}</p>

    <form method="POST" action="{{ route('installer.design.store') }}">
        @csrf

        <div class="form-check border rounded p-3 ps-5 mb-2">
            <input id="design-default" class="form-check-input" type="radio" name="design" value="default" checked>
            <label for="design-default" class="form-check-label">
                <span class="fw-semibold">{{ __('デフォルトのデザインを使う') }}</span>
                <span class="d-block small text-secondary">
                    {{ __('ヘッダー(サイト名・メニュー)、トップの大きな見出し(サイト名・説明)と新着記事、フッター(コピーライト)を用意します。インストールのあとに、管理画面のビルダーとレイアウト管理で自由に直せます。') }}
                </span>
            </label>
        </div>
        <div class="form-check border rounded p-3 ps-5 mb-4 text-secondary">
            <input id="design-builder" class="form-check-input" type="radio" name="design" value="builder" disabled>
            <label for="design-builder" class="form-check-label">
                <span class="fw-semibold">{{ __('ビルダーで作る') }}</span>
                <span class="d-block small">{{ __('準備中です。インストールのあと、管理画面の「トップページ編集」から作れます。') }}</span>
            </label>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary px-4">{{ __('次へ') }}</button>
        </div>
    </form>
@endsection
