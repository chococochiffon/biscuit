{{-- 公開予定(今日公開・今週公開予定。$scheduled は DashboardService::scheduledContents()) --}}
<div class="card h-100">
    <div class="card-header bg-transparent fw-semibold">{{ __('公開予定') }}</div>
    <div class="card-body">
        @foreach ([
            'today' => __('今日公開'),
            'this_week' => __('今週公開予定(7日以内)'),
        ] as $key => $label)
            <div @class(['mb-3' => $loop->first]) data-scheduled="{{ $key }}">
                <div class="small text-muted mb-1">{{ $label }}</div>
                @forelse ($scheduled[$key] as $content)
                    <div class="d-flex align-items-baseline gap-2 py-1">
                        <span class="small text-nowrap text-muted">{{ $content['publish_at']->format($key === 'today' ? 'H:i' : 'm/d H:i') }}</span>
                        <span class="small text-muted text-nowrap">{{ $content['type'] }}</span>
                        <a href="{{ $content['edit_url'] }}" class="text-truncate">{{ $content['title'] }}</a>
                    </div>
                @empty
                    <div class="small text-muted">{{ __('予定はありません。') }}</div>
                @endforelse
            </div>
        @endforeach
    </div>
</div>
