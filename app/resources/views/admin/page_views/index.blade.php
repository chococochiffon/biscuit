@extends('layouts.admin')

@section('title', __('アクセス解析'))

@section('content')
    <div class="mx-auto" style="max-width: 80rem;">
        <h1 class="h5 mb-4">{{ __('アクセス解析') }}</h1>

        @include('admin.page_views._summary')

        {{-- 直近 30 日の日別の推移(折れ線グラフ。同じ値を表でも見られる) --}}
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h2 class="h6 mb-0">{{ __('直近30日のアクセス推移') }}</h2>
            <div class="page-view-chart-legend d-flex align-items-center gap-3 small text-muted" aria-hidden="true">
                <span class="d-inline-flex align-items-center gap-1"><span class="page-view-chart-key page-view-chart-key-1"></span>PV</span>
                <span class="d-inline-flex align-items-center gap-1"><span class="page-view-chart-key page-view-chart-key-2"></span>UU</span>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="page-view-chart position-relative" data-role="page-view-chart" data-daily='@json($daily)'>
                    <div data-role="page-view-chart-plot" data-label="{{ __('直近30日のアクセス推移') }}(PV・UU)"></div>
                    <div class="page-view-chart-tooltip" data-role="page-view-chart-tooltip" hidden></div>
                </div>

                <details class="mt-2">
                    <summary class="small text-muted">{{ __('表で見る') }}</summary>

                    <table class="table table-sm mb-0 mt-2 align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('日付') }}</th>
                                <th class="text-end">PV</th>
                                <th class="text-end">UU</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daily->reverse() as $day)
                                <tr>
                                    <td class="text-nowrap small">{{ \Illuminate\Support\Carbon::parse($day['date'])->isoFormat('MM/DD (ddd)') }}</td>
                                    <td class="text-end text-nowrap small">{{ number_format($day['views']) }}</td>
                                    <td class="text-end text-nowrap small">{{ number_format($day['unique_visitors']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            </div>
        </div>

        {{-- 人気コンテンツ(期間を指定できる) --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h6 mb-0">{{ __('人気コンテンツ') }}</h2>

            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('期間') }}">
                @foreach ([
                    'today' => __('今日'),
                    '7days' => __('7日'),
                    '30days' => __('30日'),
                    'month' => __('今月'),
                ] as $periodKey => $periodLabel)
                    <a
                        href="{{ route('admin.page-views.index', ['period' => $periodKey]) }}"
                        @class(['btn', 'btn-primary' => $period === $periodKey, 'btn-outline-primary' => $period !== $periodKey])
                    >{{ $periodLabel }}</a>
                @endforeach
            </div>
        </div>

        <form method="GET" action="{{ route('admin.page-views.index') }}" class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <input type="hidden" name="period" value="custom">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm w-auto" aria-label="{{ __('開始日') }}">
            <span class="text-muted">〜</span>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm w-auto" aria-label="{{ __('終了日') }}">
            <button type="submit" @class(['btn', 'btn-sm', 'btn-primary' => $period === 'custom', 'btn-outline-primary' => $period !== 'custom'])>{{ __('期間指定') }}</button>
        </form>

        <p class="small text-muted mb-2">
            {{ $from->format('Y/m/d') }} 〜 {{ $to->format('Y/m/d') }}:
            {{ number_format($rankingViews) }} PV / {{ number_format($rankingUniqueVisitors) }} UU
        </p>

        <div class="card">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="text-end" style="width: 3rem;">#</th>
                        <th>{{ __('コンテンツ') }}</th>
                        <th class="text-end">PV</th>
                        <th class="text-end">UU</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ranking as $row)
                        <tr>
                            <td class="text-end text-muted">{{ $loop->iteration }}</td>
                            <td>
                                <div class="small text-muted">{{ $row['type_label'] }}</div>
                                <div>{{ $row['label'] }}</div>
                                <div class="small text-muted text-break">{{ $row['path'] }}</div>
                            </td>
                            <td class="text-end text-nowrap">{{ number_format($row['views']) }}</td>
                            <td class="text-end text-nowrap">{{ number_format($row['unique_visitors']) }}</td>
                        </tr>
                    @empty
                        <x-admin.empty-row :colspan="4">{{ __('この期間のアクセスはありません。') }}</x-admin.empty-row>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
