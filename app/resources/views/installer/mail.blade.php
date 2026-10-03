@extends('installer.layout')

@section('title', __('メール'))

@section('content')
    <h2 class="h5">{{ __('メール') }}</h2>
    <p class="text-secondary small">
        {{ __('管理画面のログインでは、確認コードをメールで送ります。メールを送るサーバー(SMTP)を設定し、試しにメールを送って届くことを確かめます。') }}
    </p>

    @include('admin.partials._form_errors')

    <form method="POST" action="{{ route('installer.mail.store') }}">
        @csrf

        <div class="row g-3 mb-3">
            <div class="col-sm-8">
                <label for="host" class="form-label">{{ __('SMTP のホスト') }}</label>
                <input id="host" type="text" name="host" value="{{ old('host') }}" required placeholder="smtp.example.com" class="form-control @error('host') is-invalid @enderror">
            </div>
            <div class="col-sm-4">
                <label for="port" class="form-label">{{ __('ポート') }}</label>
                <input id="port" type="number" name="port" value="{{ old('port', 587) }}" required min="1" max="65535" class="form-control @error('port') is-invalid @enderror">
            </div>
        </div>

        <div class="mb-3">
            <label for="encryption" class="form-label">{{ __('暗号化') }}</label>
            <select id="encryption" name="encryption" class="form-select @error('encryption') is-invalid @enderror">
                <option value="starttls" @selected(old('encryption', 'starttls') === 'starttls')>{{ __('STARTTLS(多くは 587 番)') }}</option>
                <option value="ssl" @selected(old('encryption') === 'ssl')>{{ __('SSL/TLS(多くは 465 番)') }}</option>
            </select>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label for="username" class="form-label">{{ __('SMTP のユーザー名') }}</label>
                <input id="username" type="text" name="username" value="{{ old('username') }}" autocomplete="off" class="form-control @error('username') is-invalid @enderror">
            </div>
            <div class="col-sm-6">
                <label for="password" class="form-label">{{ __('SMTP のパスワード') }}</label>
                <input id="password" type="password" name="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
            </div>
        </div>

        <div class="mb-3">
            <label for="from_address" class="form-label">{{ __('送信元のメールアドレス') }}</label>
            <input id="from_address" type="email" name="from_address" value="{{ old('from_address') }}" required placeholder="no-reply@example.com" class="form-control @error('from_address') is-invalid @enderror">
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
