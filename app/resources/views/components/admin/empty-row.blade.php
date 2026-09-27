@props([
    'colspan',
])

{{-- 一覧の表で、表示するデータがないときの行(スロットにメッセージを入れる) --}}
<tr>
    <td colspan="{{ $colspan }}" class="text-center text-muted py-4">{{ $slot }}</td>
</tr>
