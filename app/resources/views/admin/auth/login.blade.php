<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('管理者ログイン') }} - {{ config('app.name', 'Laravel') }}</title>

        @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center vh-100">
        <div class="card shadow-sm" style="width: 100%; max-width: 24rem;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-end gap-2 mb-2">
                    @foreach (config('app.available_locales') as $availableLocale)
                        <a
                            href="{{ route('locale.update', $availableLocale) }}"
                            class="small {{ app()->getLocale() === $availableLocale ? 'fw-semibold text-decoration-none text-body' : 'text-secondary' }}"
                        >
                            {{ ['ja' => '日本語', 'en' => 'English'][$availableLocale] ?? $availableLocale }}
                        </a>
                    @endforeach
                </div>

                <h1 class="h4 text-center mb-4">{{ __('管理者ログイン') }}</h1>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">{{ __('メールアドレス') }}</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            class="form-control"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">{{ __('パスワード') }}</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            class="form-control"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        {{ __('ログイン') }}
                    </button>
                </form>
            </div>
        </div>
    </body>
</html>
