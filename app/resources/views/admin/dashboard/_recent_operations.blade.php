{{-- 最近の操作(スーパー管理者は全員の操作、ほかの管理者は自分の操作。$auditLogs は DashboardService::recentAuditLogs()) --}}
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
