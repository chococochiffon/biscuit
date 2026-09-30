{{-- コンテンツの注意事項($warnings は DashboardService::contentWarnings()。該当があるものだけ) --}}
<div class="card h-100">
    <div class="card-header bg-transparent fw-semibold">{{ __('コンテンツチェック') }}</div>
    <div class="card-body">
        @forelse ($warnings as $warning)
            <div @class(['mb-3' => ! $loop->last]) data-warning>
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                    @if ($warning['url'])
                        <a href="{{ $warning['url'] }}">{{ $warning['label'] }}</a>
                    @else
                        <span>{{ $warning['label'] }}</span>
                    @endif
                    <span class="badge rounded-pill text-bg-warning">{{ __(':count件', ['count' => number_format($warning['count'])]) }}</span>
                    @isset($warning['calculated_at'])
                        <span class="small text-muted">{{ __(':datetime 時点', ['datetime' => $warning['calculated_at']]) }}</span>
                    @endisset
                </div>
                @if ($warning['items']->isNotEmpty())
                    <ul class="small mb-0 mt-1 ps-4">
                        @foreach ($warning['items'] as $item)
                            <li>
                                @isset($item['type'])
                                    <span class="text-muted">{{ $item['type'] }}</span>
                                @endisset
                                <a href="{{ $item['edit_url'] }}">{{ $item['title'] }}</a>
                                @isset($item['links'])
                                    <div class="text-muted text-break">
                                        @foreach ($item['links'] as $link)
                                            <code>{{ $link }}</code>@unless ($loop->last), @endunless
                                        @endforeach
                                    </div>
                                @endisset
                            </li>
                        @endforeach
                        @if ($warning['count'] > $warning['items']->count())
                            <li class="list-unstyled text-muted">{{ __('ほか :count件', ['count' => number_format($warning['count'] - $warning['items']->count())]) }}</li>
                        @endif
                    </ul>
                @endif
            </div>
        @empty
            <div class="small text-muted"><i class="bi bi-check-circle text-success me-1"></i>{{ __('気になる点はありません。') }}</div>
        @endforelse
    </div>
</div>
