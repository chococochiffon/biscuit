<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ __('インストールが完了しました') }} - Biscuit CMS</title>
        @vite(['resources/css/admin.css'])
    </head>
    <body class="bg-light">
        {{-- インストールの完了の画面(ロックを作ったあとにその場で返す。/install にはもう入れない) --}}
        <div class="container py-5 text-center" style="max-width: 40rem;" data-install-complete>
            <h1 class="h4 mb-3">Biscuit CMS</h1>
            <div class="card shadow-sm">
                <div class="card-body p-5">
                    <i class="bi bi-check-circle-fill text-success fs-1"></i>
                    <h2 class="h5 mt-3">{{ __('Biscuit CMS のインストールが完了しました。') }}</h2>
                    <p class="text-secondary small">
                        {{ __('install.sh は、公開側のサイトを新しい設定で起動し直してから終わります。管理画面のログインでは、管理者のメールアドレスに確認コードが届きます。') }}
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
                        <a href="{{ $frontUrl }}" class="btn btn-outline-primary">{{ __('サイトを見る') }}</a>
                        <a href="{{ $adminUrl }}" class="btn btn-primary">{{ __('管理画面へ') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
