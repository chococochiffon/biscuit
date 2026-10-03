{{-- インストールの確認(HealthChecker)の項目の一覧。満たしていない項目は failIcon(必須は赤、推奨は黄)で出す --}}
<ul class="list-group" data-finalize-checks="{{ $role }}">
    @foreach ($checks as $check)
        <li class="list-group-item d-flex align-items-start gap-2 small" data-check="{{ $check['key'] }}" data-ok="{{ $check['ok'] ? 'true' : 'false' }}">
            <i class="bi {{ $check['ok'] ? 'bi-check-circle-fill text-success' : $failIcon }} mt-1"></i>
            <span class="flex-grow-1">{{ $check['label'] }}</span>
            @if ($check['detail'])
                <span class="text-secondary text-end text-break">{{ $check['detail'] }}</span>
            @endif
        </li>
    @endforeach
</ul>
