{{-- システム情報(正常時も小さく表示する。スーパー管理者だけ。$systemStatus は SystemStatusService の info・errors・warnings) --}}
@if ($systemStatus)
    @php($systemInfo = $systemStatus['info'])
    <div class="small text-muted d-flex flex-wrap gap-3 border-top pt-3" data-system-info>
        <span>
            Biscuit {{ $systemInfo['biscuit_version'] }}
            @if ($systemStatus['update'])
                <a href="{{ $systemStatus['update']['url'] }}" target="_blank" rel="noopener noreferrer" class="badge text-bg-info text-decoration-none ms-1">{{ __('最新 :version', ['version' => $systemStatus['update']['version']]) }}</a>
            @endif
        </span>
        <span>Laravel {{ $systemInfo['laravel_version'] }}</span>
        <span>PHP {{ $systemInfo['php_version'] }}</span>
        <span>{{ __('環境') }}: {{ $systemInfo['environment'] }}@if ($systemInfo['debug']) (debug)@endif</span>
        @if ($systemInfo['disk_free'] !== null && $systemInfo['disk_total'])
            <span>{{ __('ディスクの空き') }}: {{ \App\Support\FileSize::format($systemInfo['disk_free']) }} / {{ \App\Support\FileSize::format($systemInfo['disk_total']) }}</span>
        @endif
        <span>{{ __('ログ') }}: {{ \App\Support\FileSize::format($systemInfo['log_bytes']) }}</span>
        <span>{{ __('直近 :hours 時間のエラー', ['hours' => config('biscuit.error_log_hours')]) }}: {{ number_format($systemStatus['errors']['count']) }}</span>
    </div>
@endif
