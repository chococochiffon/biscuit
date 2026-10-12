@extends('layouts.admin')

@section('title', __('二段階認証'))

@section('content')
    <div class="mx-auto" style="max-width: 36rem;">
        <h1 class="h5 mb-3">{{ __('二段階認証') }}</h1>
        <p class="text-secondary small">
            {{ __('ログインのときに、パスワードのあとに入れるコードの受け取り方を選びます。認証アプリを使うと、メールを待たずにログインできます。認証アプリを使えないとき(機種変更など)は、ログインの画面の「メールにコードを送る」でメールの確認コードを使えます。') }}
        </p>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                    <span class="fw-semibold">{{ __('今の方法') }}</span>
                    <span class="badge {{ $administrator->usesTotp() ? 'text-bg-success' : 'text-bg-secondary' }}" data-two-factor-method="{{ $administrator->usesTotp() ? 'totp' : 'email' }}">
                        {{ ($administrator->usesTotp() ? \App\Enums\TwoFactorMethod::Totp : \App\Enums\TwoFactorMethod::Email)->label() }}
                    </span>
                </div>
                @if ($administrator->usesTotp())
                    <div class="small text-secondary">{{ __('設定した日時') }}: {{ $administrator->two_factor_confirmed_at?->format('Y/m/d H:i') }}</div>
                @else
                    <div class="small text-secondary">{{ __(':email に確認コードを送ります。', ['email' => $administrator->email]) }}</div>
                @endif
            </div>
        </div>

        @include('admin.partials._form_errors')

        @if ($administrator->usesTotp())
            {{-- 認証アプリをやめてメールに戻す(今のパスワードで本人を確かめる) --}}
            <form method="POST" action="{{ route('admin.two-factor.destroy') }}" class="card card-body" data-two-factor-disable>
                @csrf
                @method('DELETE')
                <h2 class="h6">{{ __('メールの確認コードに戻す') }}</h2>
                <p class="small text-secondary">{{ __('端末を替えるときは、いったんメールに戻してから、新しい端末で設定し直してください。') }}</p>
                <div class="mb-3">
                    <label for="current_password" class="form-label">{{ __('今のパスワード') }}</label>
                    <input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="form-control @error('current_password') is-invalid @enderror">
                </div>
                <div>
                    <button type="submit" class="btn btn-outline-danger">{{ __('メールに戻す') }}</button>
                </div>
            </form>
        @elseif ($setup)
            {{-- 認証アプリの設定: QR コードを読み取り、表示されたコードを入れて確かめる --}}
            <form method="POST" action="{{ route('admin.two-factor.update') }}" class="card card-body" data-two-factor-setup>
                @csrf
                @method('PUT')
                <h2 class="h6">{{ __('認証アプリを設定する') }}</h2>
                <ol class="small ps-3">
                    <li>{{ __('スマートフォンに Google Authenticator などの認証アプリを入れます。') }}</li>
                    <li>{{ __('認証アプリでこの QR コードを読み取ります(読み取れないときは、下の設定キーを入れます)。') }}</li>
                    <li>{{ __('認証アプリに表示された 6 桁のコードを入れて「設定する」を押します。') }}</li>
                </ol>
                <div class="text-center my-2" data-two-factor-qr>{!! $qrCode !!}</div>
                <div class="small text-center mb-3">
                    {{ __('設定キー') }}: <code class="user-select-all" data-two-factor-secret>{{ trim(chunk_split($secret, 4, ' ')) }}</code>
                </div>
                <div class="mb-3">
                    <label for="code" class="form-label">{{ __('認証アプリのコード') }}</label>
                    <input id="code" type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" required autocomplete="one-time-code" class="form-control text-center fs-4 @error('code') is-invalid @enderror" style="letter-spacing: 0.5rem;">
                </div>
                <div class="d-flex align-items-center gap-3">
                    <button type="submit" class="btn btn-primary">{{ __('設定する') }}</button>
                    <a href="{{ route('admin.two-factor.show') }}" class="text-secondary">{{ __('キャンセル') }}</a>
                </div>
            </form>
        @else
            <a href="{{ route('admin.two-factor.show', ['setup' => 1]) }}" class="btn btn-primary" data-two-factor-start>
                <i class="bi bi-phone"></i> {{ __('認証アプリ(Google Authenticator)を設定する') }}
            </a>
        @endif
    </div>
@endsection
