{{-- 招待のメール(UserInvitationNotification)。ヘッダー・フッターはアプリ名ではなくサイト名にする --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="$siteUrl">
{{ $siteTitle }}
</x-mail::header>
</x-slot:header>

{{ __(':site の管理者から、マイページへの招待が届きました。', ['site' => $siteTitle]) }}

{{ __('下のボタンからプロフィールとパスワードを登録すると、マイページにログインできるようになります。') }}

<x-mail::button :url="$invitationUrl">
{{ __('プロフィールを登録する') }}
</x-mail::button>

{{ __('このリンクの有効期限は :hours 時間です。期限が切れた場合は、管理者に招待の再送を依頼してください。', ['hours' => $expireHours]) }}

{{ __('お心当たりがない場合は、このメールを破棄してください。') }}

<x-slot:subcopy>
<x-mail::subcopy>
{{ __('ボタンを押せない場合は、次の URL をブラウザに貼り付けて開いてください。') }}
<span class="break-all">[{{ $invitationUrl }}]({{ $invitationUrl }})</span>
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $siteTitle }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
