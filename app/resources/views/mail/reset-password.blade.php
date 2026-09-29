{{-- パスワード再設定のメール(ResetPasswordNotification)。ヘッダー・フッターはアプリ名ではなくサイト名にする --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="$siteUrl">
{{ $siteTitle }}
</x-mail::header>
</x-slot:header>

{{ __('パスワード再設定のご依頼を受け付けました。') }}

{{ __('下のボタンから、新しいパスワードを設定してください。') }}

<x-mail::button :url="$resetUrl">
{{ __('パスワードを再設定する') }}
</x-mail::button>

{{ __('このリンクの有効期限は :minutes 分です。', ['minutes' => $expireMinutes]) }}

{{ __('お心当たりがない場合は、このメールを破棄してください。パスワードは変わりません。') }}

<x-slot:subcopy>
<x-mail::subcopy>
{{ __('ボタンを押せない場合は、次の URL をブラウザに貼り付けて開いてください。') }}
<span class="break-all">[{{ $resetUrl }}]({{ $resetUrl }})</span>
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $siteTitle }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
