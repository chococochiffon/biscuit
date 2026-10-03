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
            <input id="name" type="text" name="name" value="{{ $name }}" required maxlength="255" class="form-control @error('name') is-invalid @enderror">
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">{{ __('メールアドレス') }}</label>
            <input id="email" type="email" name="email" value="{{ $email }}" required maxlength="255" autocomplete="username" class="form-control @error('email') is-invalid @enderror">
        </div>

        @if ($generatedPassword)
            <div class="alert alert-info small" role="status" data-generated-password>
                {{ __('安全なパスワードを生成して、下の欄に入れました。ログインに使うので、必ず控えてから進んでください。') }}
                <div class="mt-1"><code class="user-select-all">{{ $generatedPassword }}</code></div>
            </div>
        @endif

        <div class="mb-3">
            <label for="password" class="form-label">{{ __('パスワード') }}</label>
            <input id="password" type="password" name="password" value="{{ $generatedPassword }}" required autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
            <div class="form-text">{{ __(':min 文字以上。初期のパスワード・よく使われるパスワードと、メールアドレス・表示名と同じ値は使えません。', ['min' => config('installer.admin_password_min_length')]) }}</div>
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">{{ __('パスワード(確認)') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" value="{{ $generatedPassword }}" required autocomplete="new-password" class="form-control">
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-between">
            <button type="submit" formaction="{{ route('installer.administrator.generate') }}" formnovalidate class="btn btn-outline-secondary">
                <i class="bi bi-shuffle"></i> {{ __('安全なパスワードを生成') }}
            </button>
            <button type="submit" class="btn btn-primary px-4">{{ __('次へ') }}</button>
        </div>
    </form>
@endsection
