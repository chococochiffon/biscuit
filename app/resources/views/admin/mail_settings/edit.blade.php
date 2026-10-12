@extends('layouts.admin')

@section('title', __('メールの設定'))

@section('content')
    {{-- 選んだ送り方の欄だけを出す(JavaScript を使わない。隠した欄の必須はサーバー側で確かめる) --}}
    <style>
        form[data-mail-settings]:has(#driver_resend:checked) [data-mail-driver="smtp"],
        form[data-mail-settings]:has(#driver_smtp:checked) [data-mail-driver="resend"] {
            display: none;
        }
    </style>

    <div class="mx-auto" style="max-width: 48rem;">
        <h1 class="h5 mb-3">{{ __('メールの設定') }}</h1>
        <p class="text-secondary small">
            {{ __('ログインの確認コード・パスワードの再設定・招待・お知らせなど、システムが送るメールの送り方を設定します。保存の前に試しのメールを送り、届いたときだけ保存します(設定を間違えると、確認コードが届かず管理画面にログインできなくなるため)。') }}
        </p>

        <div class="alert alert-light border small" data-mail-source="{{ $current['source'] }}">
            @if ($current['source'] === 'database')
                <i class="bi bi-database"></i> {{ __('今は、この画面で保存した設定を使っています(.env の MAIL_* より優先します)。') }}
            @else
                <i class="bi bi-file-earmark-text"></i> {{ __('今は .env の設定(MAIL_*)を使っています。この画面で保存すると、こちらの設定を優先します。') }}
            @endif
            <div class="mt-1 text-secondary">
                {{ $current['driver'] === 'resend' ? 'Resend' : 'SMTP' }}
                @if ($current['driver'] === 'smtp' && filled($current['host']))
                    ({{ $current['host'] }}:{{ $current['port'] }})
                @endif
                / {{ __('送信元') }}: {{ $current['from_address'] }}
            </div>
        </div>

        @include('admin.partials._form_errors')

        <form method="POST" action="{{ route('admin.mail-settings.update') }}" class="card card-body mb-4" data-mail-settings>
            @csrf
            @method('PUT')

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
                    <select id="encryption" name="encryption" class="form-select form-select-auto @error('encryption') is-invalid @enderror">
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
                        @if ($current['driver'] === 'smtp' && filled($current['password']))
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
                    @if ($current['driver'] === 'resend' && filled($current['api_key']))
                        {{ __('空のままにすると、今の API キーを使います。') }}
                    @endif
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-7">
                    <label for="from_address" class="form-label">{{ __('送信元のメールアドレス') }}</label>
                    <input id="from_address" type="email" name="from_address" value="{{ $values['from_address'] }}" required placeholder="no-reply@example.com" class="form-control @error('from_address') is-invalid @enderror">
                </div>
                <div class="col-sm-5">
                    <label for="from_name" class="form-label">{{ __('送信元の名前') }}</label>
                    <input id="from_name" type="text" name="from_name" value="{{ $values['from_name'] }}" maxlength="255" class="form-control @error('from_name') is-invalid @enderror">
                    <div class="form-text">{{ __('空のままにすると .env の MAIL_FROM_NAME を使います(ログインの確認コードなどはサイト名で送ります)。') }}</div>
                </div>
            </div>

            <div class="mb-4">
                <label for="test_to" class="form-label">{{ __('試しに送る宛先') }}</label>
                <input id="test_to" type="email" name="test_to" value="{{ old('test_to', auth('admin')->user()->email) }}" required class="form-control @error('test_to') is-invalid @enderror">
                <div class="form-text">{{ __('受け取れるメールアドレスを入れてください(管理者のメールアドレスなど)。') }}</div>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">{{ __('試しに送って保存する') }}</button>
            </div>
        </form>

        @if ($setting !== null)
            {{-- 画面の設定をやめて .env の設定に戻す --}}
            <form method="POST" action="{{ route('admin.mail-settings.destroy') }}" onsubmit="return confirm(@js(__('画面の設定をやめて、.env の設定に戻しますか?')))" data-mail-settings-reset>
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('.env の設定に戻す') }}</button>
            </form>
        @endif
    </div>
@endsection
