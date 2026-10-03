{{-- 新しい Biscuit のバージョンのお知らせ(UpdateAvailableNotification)。ヘッダー・フッターはアプリ名ではなくサイト名にする --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="$siteUrl">
{{ $siteTitle }}
</x-mail::header>
</x-slot:header>

{{ __('Biscuit の新しいバージョンが公開されています。') }}

<x-mail::panel>
{{ __('今のバージョン') }}: {{ $currentVersion }}<br>
{{ __('新しいバージョン') }}: {{ $release['version'] }}@if ($release['published_at']) ({{ $release['published_at']->format('Y/m/d') }})@endif
</x-mail::panel>

@if ($release['notes'] !== '')
{{ $release['notes'] }}

@endif
<x-mail::button :url="$release['url']">
{{ __('リリースノートを見る') }}
</x-mail::button>

{{ __('このメールは、管理画面のスーパー管理者にお送りしています。') }}

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $siteTitle }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
