<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', __('ページビルダー')) - {{ config('app.name', 'Laravel') }}</title>

        {{ \Illuminate\Support\Facades\Vite::fonts('nunito') }}
        {{-- エディタで表示する文言(現在の言語に翻訳済み。エディタでは t('日本語の原文') で参照する) --}}
        <script>
            window.builderTranslations = @json(\App\Support\PageBuilderJsTranslations::translated());
        </script>
        @vite(['resources/css/admin.css', 'resources/js/builder.ts'])
    </head>
    {{-- ページビルダーのエディタは画面全体を使うため、管理画面のサイドメニューは出さない --}}
    <body class="bg-light">
        @yield('content')
    </body>
</html>
