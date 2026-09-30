{{-- システムの警告(問題があるときだけ目立たせる。スーパー管理者だけ。$systemStatus は SystemStatusService の info・errors・warnings) --}}
@if ($systemStatus && $systemStatus['warnings'] !== [])
    <div class="mb-4" data-system-warnings>
        @foreach ($systemStatus['warnings'] as $systemWarning)
            <div class="alert alert-{{ $systemWarning['level'] }} d-flex align-items-start gap-2 py-2 mb-2" role="alert">
                <i class="bi bi-exclamation-octagon-fill mt-1"></i>
                <div>{{ $systemWarning['message'] }}</div>
            </div>
        @endforeach
        @if ($systemStatus['errors']['last_message'])
            <div class="small text-muted">
                {{ __('最後のエラー') }}({{ $systemStatus['errors']['last_at']->format('m/d H:i') }}): <code>{{ $systemStatus['errors']['last_message'] }}</code>
            </div>
        @endif
    </div>
@endif
