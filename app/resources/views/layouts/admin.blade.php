<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', __('管理画面')) - {{ config('app.name', 'Laravel') }}</title>

        @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    </head>
    <body class="bg-light">
        <nav class="navbar navbar-expand navbar-light bg-white border-bottom">
            <div class="container flex-wrap">
                <div class="d-flex w-100 align-items-center justify-content-between">
                    <a href="{{ route('admin.index') }}" class="navbar-brand fw-semibold mb-0 fs-2">{{ __('システム管理') }}</a>

                    <div class="d-flex flex-column align-items-end gap-1">
                        @auth('admin')
                            <div class="small text-secondary">{{ auth('admin')->user()->name }}</div>
                        @endauth

                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center gap-2">
                                @foreach (config('app.available_locales') as $availableLocale)
                                    <a
                                        href="{{ route('locale.update', $availableLocale) }}"
                                        class="small {{ app()->getLocale() === $availableLocale ? 'fw-semibold text-decoration-none text-body' : 'text-secondary' }}"
                                    >
                                        {{ ['ja' => '日本語', 'en' => 'English'][$availableLocale] ?? $availableLocale }}
                                    </a>
                                @endforeach
                            </div>

                            @auth('admin')
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-link nav-link text-secondary p-0 small">
                                        {{ __('ログアウト') }}
                                    </button>
                                </form>
                            @endauth
                        </div>
                    </div>
                </div>

                <ul class="navbar-nav w-100 mt-2">
                    <li class="nav-item">
                        <a href="{{ route('admin.index') }}" class="nav-link">{{ __('管理者一覧') }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.users.index') }}" class="nav-link">{{ __('ユーザー一覧') }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.articles.index') }}" class="nav-link">{{ __('記事一覧') }}</a>
                    </li>
                    <li class="nav-item">
                        <a
                            href="{{ $currentSiteSetting ? route('admin.site-settings.show', $currentSiteSetting) : route('admin.site-settings.create') }}"
                            class="nav-link"
                        >{{ __('サイト設定') }}</a>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="container py-4">
            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </body>
</html>
