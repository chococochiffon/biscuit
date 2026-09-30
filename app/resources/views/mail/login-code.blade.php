{{-- 二段階認証の確認コードのメール(LoginCodeNotification)。ヘッダー・フッターはアプリ名ではなくサイト名にする --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="$siteUrl">
{{ $siteTitle }}
</x-mail::header>
</x-slot:header>

{{ __('ログインの確認コードをお送りします。ログイン画面に次のコードを入力してください。') }}

<x-mail::panel>
<span style="font-size: 24px; font-weight: bold; letter-spacing: 6px;">{{ $code }}</span>
</x-mail::panel>

{{ __('このコードの有効期限は :minutes 分です。', ['minutes' => $expireMinutes]) }}

{{ __('お心当たりがない場合は、このメールを破棄してください。パスワードが他人に知られている可能性があるため、パスワードの変更をおすすめします。') }}

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $siteTitle }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
