{{-- 今日・昨日・今月・累計の PV と UU(アクセス解析とダッシュボードで共通。$summary は PageViewStatsService::summary()) --}}
<div class="row g-3 mb-4">
    @foreach ([
        'today' => __('今日'),
        'yesterday' => __('昨日'),
        'this_month' => __('今月'),
        'total' => __('累計'),
    ] as $key => $label)
        <div class="col-6 col-lg-3">
            <div class="card h-100" data-summary="{{ $key }}">
                <div class="card-body">
                    <div class="small text-muted mb-1">{{ $label }}</div>
                    <div class="fs-4 fw-semibold">{{ number_format($summary[$key]['views']) }} <span class="fs-6 fw-normal text-muted">PV</span></div>
                    <div class="small text-muted">{{ number_format($summary[$key]['unique_visitors']) }} UU</div>
                </div>
            </div>
        </div>
    @endforeach
</div>
