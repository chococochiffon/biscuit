@extends('installer.layout')

@section('title', __('管理者'))

@section('content')
    <h2 class="h5">{{ __('管理者') }}</h2>
    <p class="text-secondary small">
        {{ __('最初の管理者(スーパー管理者)を作ります。ログインでは、このメールアドレスに確認コードを送ります。') }}
    </p>

    @include('admin.partials._form_errors')

    <form method="POST" action="{{ route('installer.administrator.store') }}">
        @csrf

        <div class="mb-3">
            <label for="name" class="form-label">{{ __('表示名') }}</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="255" class="form-control @error('name') is-invalid @enderror">
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">{{ __('メールアドレス') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="username" class="form-control @error('email') is-invalid @enderror">
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">{{ __('パスワード') }}</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
            <div class="form-text">{{ __(':min 文字以上。初期のパスワード・よく使われるパスワードと、メールアドレス・表示名と同じ値は使えません。', ['min' => config('installer.admin_password_min_length')]) }}</div>
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">{{ __('パスワード(確認)') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="form-control">
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary px-4">{{ __('次へ') }}</button>
        </div>
    </form>
@endsection
