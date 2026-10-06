@extends('installer.layout')

@section('title', __('メール'))

@section('content')
    <h2 class="h5">{{ __('メール') }}</h2>
    <p class="text-secondary small">
        {{ __('管理画面のログインでは、確認コードをメールで送ります。メールの送り方(SMTP か Resend)を設定し、試しにメールを送って届くことを確かめます。') }}
    </p>

    @push('head')
        {{-- 選んだ送り方の欄だけを出す(JavaScript を使わない。隠した欄の必須はサーバー側で確かめる) --}}
        <style>
            form[data-installer-mail]:has(#driver_resend:checked) [data-mail-driver="smtp"],
            form[data-installer-mail]:has(#driver_smtp:checked) [data-mail-driver="resend"] {
                display: none;
            }
        </style>
    @endpush

    @include('admin.partials._form_errors')

    <form method="POST" action="{{ route('installer.mail.store') }}" data-installer-mail>
        @csrf

        <fieldset class="mb-3">
            <legend class="form-label fs-6">{{ __('メールの送り方') }}</legend>
            <div class="form-check">
                <input id="driver_smtp" type="radio" name="driver" value="smtp" @checked($values['driver'] === 'smtp') class="form-check-input">
                <label for="driver_smtp" class="form-check-label">{{ __('SMTP サーバー') }}</label>
            </div>
            <div class="form-check">
                <input id="driver_resend" type="radio" name="driver" value="resend" @checked($values['driver'] === 'resend') class="form-check-input">
                <label for="driver_resend" class="form-check-label">{{ __('Resend(API キー)') }}</label>
            </div>
        </fieldset>

        <div data-mail-driver="smtp">
            <div class="row g-3 mb-3">
                <div class="col-sm-8">
                    <label for="host" class="form-label">{{ __('SMTP のホスト') }}</label>
                    <input id="host" type="text" name="host" value="{{ $values['host'] }}" placeholder="smtp.example.com" class="form-control @error('host') is-invalid @enderror">
                </div>
                <div class="col-sm-4">
                    <label for="port" class="form-label">{{ __('ポート') }}</label>
                    <input id="port" type="number" name="port" value="{{ $values['port'] }}" min="1" max="65535" class="form-control @error('port') is-invalid @enderror">
                </div>
            </div>

            <div class="mb-3">
                <label for="encryption" class="form-label">{{ __('暗号化') }}</label>
                <select id="encryption" name="encryption" class="form-select @error('encryption') is-invalid @enderror">
                    <option value="starttls" @selected($values['encryption'] === 'starttls')>{{ __('STARTTLS(多くは 587 番)') }}</option>
                    <option value="ssl" @selected($values['encryption'] === 'ssl')>{{ __('SSL/TLS(多くは 465 番)') }}</option>
                </select>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label for="username" class="form-label">{{ __('SMTP のユーザー名') }}</label>
                    <input id="username" type="text" name="username" value="{{ $values['username'] }}" autocomplete="off" class="form-control @error('username') is-invalid @enderror">
                </div>
                <div class="col-sm-6">
                    <label for="password" class="form-label">{{ __('SMTP のパスワード') }}</label>
                    <input id="password" type="password" name="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
                    @if ($current === 'smtp')
                        <div class="form-text">{{ __('空のままにすると、今のパスワードを使います。') }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="mb-3" data-mail-driver="resend">
            <label for="api_key" class="form-label">{{ __('Resend の API キー') }}</label>
            <input id="api_key" type="password" name="api_key" autocomplete="off" placeholder="re_..." class="form-control @error('api_key') is-invalid @enderror">
            <div class="form-text">
                {{ __('Resend の管理画面の API Keys で作った、送信の権限(Sending access)のキーを入れてください。送信元のメールアドレスのドメインは、先に Resend の Domains で認証しておきます。') }}
                @if ($current === 'resend')
                    {{ __('空のままにすると、今の API キーを使います。') }}
                @endif
            </div>
        </div>

        <div class="mb-3">
            <label for="from_address" class="form-label">{{ __('送信元のメールアドレス') }}</label>
            <input id="from_address" type="email" name="from_address" value="{{ $values['from_address'] }}" required placeholder="no-reply@example.com" class="form-control @error('from_address') is-invalid @enderror">
        </div>

        <div class="mb-4">
            <label for="test_to" class="form-label">{{ __('試しに送る宛先') }}</label>
            <input id="test_to" type="email" name="test_to" value="{{ old('test_to') }}" required class="form-control @error('test_to') is-invalid @enderror">
            <div class="form-text">{{ __('受け取れるメールアドレスを入れてください(管理者のメールアドレスなど)。') }}</div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary px-4">{{ __('試しに送って次へ') }}</button>
        </div>
    </form>
@endsection
