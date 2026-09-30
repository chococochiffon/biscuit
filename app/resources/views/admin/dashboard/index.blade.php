@extends('layouts.admin')

@section('title', __('ダッシュボード'))

@section('content')
    <div class="mx-auto" style="max-width: 80rem;">
        <h1 class="h5 mb-4">{{ __('ダッシュボード') }}</h1>

        {{-- コンテンツの状況(記事・固定ページの状態ごとの件数) --}}
        <div class="row g-3 mb-4">
            @foreach ([
                'article' => ['label' => __('記事'), 'url' => route('admin.articles.index'), 'items' => [
                    'published' => __('公開中'),
                    'scheduled' => __('予約公開'),
                    'draft' => __('下書き'),
                    'unpublished' => __('非公開'),
                ]],
                'single_page' => ['label' => __('固定ページ'), 'url' => route('admin.single-pages.index'), 'items' => [
                    'published' => __('公開中'),
                    'scheduled' => __('予約公開'),
                    'unpublished' => __('非公開'),
                ]],
            ] as $type => $card)
                <div class="col-md-6">
                    <div class="card h-100" data-content-counts="{{ $type }}">
                        <div class="card-body d-flex flex-wrap align-items-center gap-4">
                            <a href="{{ $card['url'] }}" class="text-decoration-none text-reset">
                                <div class="small text-muted">{{ $card['label'] }}</div>
                                <div class="fs-3 fw-semibold">{{ number_format($counts[$type]['total']) }}</div>
                            </a>
                            <dl class="d-flex flex-wrap gap-4 mb-0">
                                @foreach ($card['items'] as $key => $label)
                                    <div data-count="{{ $key }}">
                                        <dt class="small text-muted fw-normal">{{ $label }}</dt>
                                        <dd class="fs-5 mb-0">{{ number_format($counts[$type][$key]) }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-3 mb-4">
            {{-- 最近編集したコンテンツ --}}
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header bg-transparent fw-semibold">{{ __('最近編集したコンテンツ') }}</div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr class="small">
                                    <th>{{ __('タイトル') }}</th>
                                    <th>{{ __('状態') }}</th>
                                    <th>{{ __('更新者') }}</th>
                                    <th>{{ __('更新日時') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentContents as $content)
                                    <tr>
                                        <td>
                                            <div class="small text-muted">{{ $content['type'] }}</div>
                                            {{ $content['title'] }}
                                        </td>
                                        <td><span class="badge text-bg-{{ $content['status_color'] }}">{{ $content['status'] }}</span></td>
                                        <td class="small">{{ $content['updated_by'] ?? '—' }}</td>
                                        <td class="small text-nowrap">{{ $content['updated_at']?->format('Y/m/d H:i') }}</td>
                                        <td class="text-end">
                                            <a href="{{ $content['edit_url'] }}" class="btn btn-sm btn-outline-secondary text-nowrap">{{ __('編集') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <x-admin.empty-row :colspan="5">{{ __('まだコンテンツがありません。') }}</x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- クイック操作 --}}
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-transparent fw-semibold">{{ __('クイック操作') }}</div>
                    <div class="list-group list-group-flush">
                        @foreach ([
                            ['route' => 'admin.articles.create', 'icon' => 'bi-pencil-square', 'label' => __('記事を書く')],
                            ['route' => 'admin.single-pages.create', 'icon' => 'bi-file-earmark-plus', 'label' => __('固定ページを作成')],
                            ['route' => 'admin.gallery-images.create', 'icon' => 'bi-image', 'label' => __('ギャラリー画像を追加')],
                            ['route' => 'admin.users.invite', 'icon' => 'bi-person-plus', 'label' => __('ユーザーを招待')],
                            ['route' => 'admin.layouts.edit', 'icon' => 'bi-layout-text-window', 'label' => __('レイアウト管理')],
                        ] as $action)
                            <a href="{{ route($action['route']) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                                <i class="bi {{ $action['icon'] }} text-primary"></i>{{ $action['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            {{-- 予約公開 --}}
            <div class="col-lg-6">
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
            </div>

            {{-- コンテンツの注意事項 --}}
            <div class="col-lg-6">
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
                                </div>
                                @if ($warning['items']->isNotEmpty())
                                    <ul class="small mb-0 mt-1 ps-4">
                                        @foreach ($warning['items'] as $item)
                                            <li><a href="{{ $item['edit_url'] }}">{{ $item['title'] }}</a></li>
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
            </div>
        </div>

        {{-- 最近の操作(スーパー管理者は全員の操作、ほかの管理者は自分の操作) --}}
        <div class="card mb-4">
            <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
                <span class="fw-semibold">{{ __('最近の操作') }}</span>
                @can('view-audit-logs')
                    <a href="{{ route('admin.audit-logs.index') }}" class="small link-primary">{{ __('操作ログを見る') }}</a>
                @endcan
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($auditLogs as $auditLog)
                    <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
                        <span class="small text-muted text-nowrap">{{ $auditLog->created_at?->format('m/d H:i') }}</span>
                        <span class="badge text-bg-{{ $auditLog->action->badgeColor() }}">{{ $auditLog->action->label() }}</span>
                        <span>
                            @if ($auditLog->subject_type)
                                <span class="text-muted">{{ $auditLog->subjectTypeLabel() }}</span>
                            @endif
                            {{ $auditLog->subject_label ?? ($auditLog->metadata['email'] ?? '') }}
                        </span>
                        <span class="small text-muted ms-auto">{{ $auditLog->actor_name ?? '—' }}</span>
                    </li>
                @empty
                    <li class="list-group-item small text-muted">{{ __('操作の記録はまだありません。') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="mb-3 d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0">{{ __('アクセス') }}</h2>
            <a href="{{ route('admin.page-views.index') }}" class="link-primary">{{ __('アクセス解析を見る') }}</a>
        </div>

        @include('admin.page_views._summary')
    </div>
@endsection
