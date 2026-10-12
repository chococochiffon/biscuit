<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('確認コードの入力') }} - {{ config('app.name', 'Laravel') }}</title>

        @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center vh-100">
        <div class="card shadow-sm" style="width: 100%; max-width: 24rem;">
            <div class="card-body p-4">
                <h1 class="h4 text-center mb-4">{{ __('確認コードの入力') }}</h1>

                @if (session('status'))
                    <div class="alert alert-success small" role="status">{{ session('status') }}</div>
                @endif

                @include('admin.partials._form_errors')

                <p class="small text-body-secondary" data-two-factor-method="{{ $usesTotp ? 'totp' : 'email' }}">
                    @if ($usesTotp)
                        {{ __('認証アプリ(Google Authenticator など)に表示されている 6 桁のコードを入力してください。') }}
                    @else
                        {{ __(':email に確認コードを送りました。メールに書かれた 6 桁のコードを入力してください。', ['email' => $email]) }}
                    @endif
                </p>

                <form method="POST" action="{{ route('admin.login.verify.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="code" class="form-label">{{ $usesTotp ? __('認証アプリのコード') : __('確認コード') }}</label>
                        <input
                            id="code"
                            type="text"
                            name="code"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            required
                            autofocus
                            autocomplete="one-time-code"
                            class="form-control text-center fs-4"
                            style="letter-spacing: 0.5rem;"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        {{ __('ログイン') }}
                    </button>
                </form>

                <div class="d-flex justify-content-between align-items-center mt-3 small">
                    @if ($usesTotp)
                        {{-- 機種変更などで認証アプリを使えないときは、メールの確認コードでログインする --}}
                        <form method="POST" action="{{ route('admin.login.email-code') }}" data-send-email-code>
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm p-0">{{ __('メールにコードを送る') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.login.resend') }}">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm p-0">{{ __('コードを送り直す') }}</button>
                        </form>
                    @endif
                    <a href="{{ route('admin.login') }}" class="text-secondary">{{ __('ログイン画面へ戻る') }}</a>
                </div>
            </div>
        </div>
    </body>
</html>
