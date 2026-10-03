{{-- Biscuit からのお知らせ(すべての管理者。$announcements は AnnouncementService::current() の新しい順の数件)。お知らせがなければ出さない --}}
@if ($announcements !== [])
    <div class="card mt-3" data-announcements>
        <div class="card-header bg-white">
            <h2 class="h6 mb-0"><i class="bi bi-megaphone"></i> {{ __('Biscuit からのお知らせ') }}</h2>
        </div>
        <ul class="list-group list-group-flush">
            @foreach ($announcements as $announcement)
                <li class="list-group-item small">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="text-muted">{{ $announcement['date']->format('Y/m/d') }}</span>
                        @if ($announcement['level'] === 'security')
                            <span class="badge text-bg-danger">{{ __('セキュリティ') }}</span>
                        @elseif ($announcement['level'] === 'important')
                            <span class="badge text-bg-warning">{{ __('重要') }}</span>
                        @endif
                    </div>
                    <div class="fw-semibold">
                        @if ($announcement['url'])
                            <a href="{{ $announcement['url'] }}" target="_blank" rel="noopener noreferrer">{{ $announcement['title'] }}</a>
                        @else
                            {{ $announcement['title'] }}
                        @endif
                    </div>
                    @if ($announcement['body'] !== '')
                        <div class="text-secondary" style="white-space: pre-line;">{{ $announcement['body'] }}</div>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
