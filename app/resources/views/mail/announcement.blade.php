{{-- Biscuit からの重要・セキュリティのお知らせ(AnnouncementNotification)。ヘッダー・フッターはアプリ名ではなくサイト名にする --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="$siteUrl">
{{ $siteTitle }}
</x-mail::header>
</x-slot:header>

{{ __('Biscuit の開発元から、:level のお知らせが届いています。', ['level' => $announcement['level'] === 'security' ? __('セキュリティ') : __('重要')]) }}

<x-mail::panel>
**{{ $announcement['title'] }}**<br>
{{ $announcement['date']->format('Y/m/d') }}
</x-mail::panel>

@if ($announcement['body'] !== '')
{{ $announcement['body'] }}

@endif
@if ($announcement['url'])
<x-mail::button :url="$announcement['url']">
{{ __('詳しく見る') }}
</x-mail::button>
@endif

{{ __('このメールは、管理画面のスーパー管理者にお送りしています。') }}

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $siteTitle }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
