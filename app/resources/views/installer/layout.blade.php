<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        <title>@yield('title') - {{ __('Biscuit のインストール') }}</title>
        @vite(['resources/css/admin.css'])
    </head>
    <body class="bg-light">
        {{-- インストーラーの画面の枠。上に Stepper(今の段を常に表示)、下に段ごとの中身。$installer は InstallerManager、$step は今の段 --}}
        <div class="container py-5" style="max-width: 48rem;">
            <h1 class="h4 text-center mb-4">Biscuit CMS</h1>

            @include('installer._stepper')

            @if (session('error'))
                <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
            @endif

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    @yield('content')
                </div>
            </div>
        </div>
    </body>
</html>
