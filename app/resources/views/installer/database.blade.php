@extends('installer.layout')

@section('title', __('データベース'))

@section('content')
    <h2 class="h5">{{ __('データベース') }}</h2>
    <p class="text-secondary small">
        {{ __('Biscuit が使う MySQL のデータベースとユーザーを決めます。次の段で、この値でデータベースを作って接続を確かめます。') }}
    </p>

    @include('admin.partials._form_errors')

    <form method="POST" action="{{ route('installer.database.store') }}" data-database-form>
        @csrf

        <dl class="row small text-secondary mb-3">
            <dt class="col-4">{{ __('種類') }}</dt><dd class="col-8 mb-1">MySQL</dd>
            <dt class="col-4">{{ __('ホスト') }}</dt><dd class="col-8 mb-1">{{ config('installer.database.host') }}</dd>
            <dt class="col-4">{{ __('ポート') }}</dt><dd class="col-8 mb-0">{{ config('installer.database.port') }}</dd>
        </dl>

        <div class="mb-3">
            <label for="database" class="form-label">{{ __('データベース名') }}</label>
            <input id="database" type="text" name="database" value="{{ $database }}" required maxlength="64" class="form-control @error('database') is-invalid @enderror">
        </div>

        <div class="mb-3">
            <label for="username" class="form-label">{{ __('ユーザー名') }}</label>
            <input id="username" type="text" name="username" value="{{ $username }}" required maxlength="32" autocomplete="off" class="form-control @error('username') is-invalid @enderror">
        </div>

        @if ($generatedPassword)
            <div class="alert alert-info small" role="status" data-generated-password>
                {{ __('安全なパスワードを生成して、下の欄に入れました。必要なら控えてから進んでください。') }}
                <div class="mt-1"><code class="user-select-all">{{ $generatedPassword }}</code></div>
            </div>
        @endif

        <div class="mb-3">
            <label for="password" class="form-label">{{ __('パスワード') }}</label>
            <input id="password" type="password" name="password" value="{{ $generatedPassword }}" required autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
            <div class="form-text">{{ __(':min 文字以上。初期のパスワード・よく使われるパスワードと、空白と $ " \' \\ ` # は使えません。', ['min' => config('installer.db_password_min_length')]) }}</div>
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">{{ __('パスワード(確認)') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" value="{{ $generatedPassword }}" required autocomplete="new-password" class="form-control">
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-between">
            <button type="submit" formaction="{{ route('installer.database.generate') }}" formnovalidate class="btn btn-outline-secondary">
                <i class="bi bi-shuffle"></i> {{ __('安全なパスワードを生成') }}
            </button>
            <button type="submit" class="btn btn-primary px-4">{{ __('次へ') }}</button>
        </div>
    </form>
@endsection
