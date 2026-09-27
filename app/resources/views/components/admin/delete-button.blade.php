@props([
    'action',
    'confirm' => null,
])

{{-- 一覧の削除ボタン。確認ダイアログで OK したときだけ、action へ DELETE で送信する --}}
<form
    method="POST"
    action="{{ $action }}"
    {{ $attributes->class(['d-inline']) }}
    onsubmit="return confirm(@js($confirm ?? __('削除してよろしいですか?')));"
>
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('削除') }}</button>
</form>
