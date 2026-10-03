{{-- システムの警告(問題があるときだけ目立たせる)と、更新できるバージョンのお知らせ。スーパー管理者だけ。
     $systemStatus は SystemStatusService の info・errors・warnings と、UpdateCheckService の update --}}
@if ($systemStatus && ($systemStatus['warnings'] !== [] || $systemStatus['update']))
    <div class="mb-4" data-system-warnings>
        @if ($systemStatus['update'])
            @php($update = $systemStatus['update'])
            <div class="alert alert-info d-flex align-items-start gap-2 py-2 mb-2" role="status" data-update-available>
                <i class="bi bi-arrow-up-circle mt-1"></i>
                <div>
                    {{ __('Biscuit の新しいバージョン :version が公開されています(今のバージョン: :current)。', ['version' => $update['version'], 'current' => $systemStatus['info']['biscuit_version']]) }}
                    @if ($update['published_at'])
                        <span class="text-muted small">({{ $update['published_at']->format('Y/m/d') }})</span>
                    @endif
                    <a href="{{ $update['url'] }}" target="_blank" rel="noopener noreferrer">{{ __('リリースノートを見る') }}</a>
                </div>
            </div>
        @endif
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
