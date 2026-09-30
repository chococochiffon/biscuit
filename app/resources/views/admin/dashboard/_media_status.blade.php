{{-- メディア状況(画像の件数・容量・未使用の画像。$media は MediaStatsService::stats()) --}}
<div class="card h-100" data-media-status>
    <div class="card-header bg-transparent fw-semibold d-flex align-items-center justify-content-between">
        <span>{{ __('メディア状況') }}</span>
        <span class="small fw-normal text-muted">{{ __(':datetime 時点', ['datetime' => $media['calculated_at']]) }}</span>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-4 mb-3">
            <div>
                <div class="small text-muted">{{ __('画像') }}</div>
                <div class="fs-4 fw-semibold">{{ number_format($media['total_count']) }}</div>
            </div>
            <div>
                <div class="small text-muted">{{ __('使用容量') }}</div>
                <div class="fs-4 fw-semibold">{{ \App\Support\FileSize::format($media['total_bytes']) }}</div>
            </div>
            <div data-unused>
                <div class="small text-muted">{{ __('未使用') }}</div>
                <div @class(['fs-4 fw-semibold', 'text-warning-emphasis' => $media['unused_count'] > 0])>
                    {{ number_format($media['unused_count']) }}
                    <span class="fs-6 fw-normal text-muted">({{ \App\Support\FileSize::format($media['unused_bytes']) }})</span>
                </div>
            </div>
        </div>
        <table class="table table-sm small mb-3">
            <tbody>
                @foreach ($media['directories'] as $directory)
                    <tr>
                        <td class="text-muted">{{ $directory['label'] }}</td>
                        <td class="text-end">{{ __(':count件', ['count' => number_format($directory['count'])]) }}</td>
                        <td class="text-end text-nowrap">{{ \App\Support\FileSize::format($directory['bytes']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($media['unused_items'] !== [])
            <div class="small text-muted mb-1">{{ __('どこからも使われていない画像(新しい順)') }}</div>
            <ul class="small mb-0 ps-3">
                @foreach ($media['unused_items'] as $path)
                    <li class="text-break"><code>{{ $path }}</code></li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
