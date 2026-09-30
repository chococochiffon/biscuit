@extends('layouts.admin')

@section('title', __('アクセス解析'))

@section('content')
    <div class="mx-auto" style="max-width: 80rem;">
        <h1 class="h5 mb-4">{{ __('アクセス解析') }}</h1>

        {{-- 今日・昨日・今月・累計の PV と UU --}}
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

        <div class="row g-4">
            {{-- 人気コンテンツ(期間を指定できる) --}}
            <div class="col-xl-7">
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

            {{-- 直近 30 日の日別の推移 --}}
            <div class="col-xl-5">
                <h2 class="h6 mb-3">{{ __('直近30日のアクセス推移') }}</h2>

                <div class="card">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('日付') }}</th>
                                <th class="w-50"></th>
                                <th class="text-end">PV</th>
                                <th class="text-end">UU</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daily->reverse() as $day)
                                <tr>
                                    <td class="text-nowrap small">{{ \Illuminate\Support\Carbon::parse($day['date'])->isoFormat('MM/DD (ddd)') }}</td>
                                    <td>
                                        <div class="progress" style="height: 0.5rem;" role="presentation">
                                            <div class="progress-bar" style="width: {{ round($day['views'] / $dailyMax * 100, 1) }}%;"></div>
                                        </div>
                                    </td>
                                    <td class="text-end text-nowrap small">{{ number_format($day['views']) }}</td>
                                    <td class="text-end text-nowrap small text-muted">{{ number_format($day['unique_visitors']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
