{{-- ユーザー状況(人数・管理者の権限ごとの人数・最近のログイン。$userStatus は DashboardService::userStatus()) --}}
<div class="card h-100" data-user-status>
    <div class="card-header bg-transparent fw-semibold">{{ __('ユーザー状況') }}</div>
    <div class="card-body">
        <x-admin.count-summary
            class="mb-3"
            size="fs-4"
            :label="__('ユーザー')"
            :url="route('admin.users.index')"
            :counts="$userStatus['users']"
            :items="['active' => __('有効'), 'invited' => __('招待中'), 'skip_approval' => __('承認なしで公開')]"
        />
        <div class="small mb-3">
            <a href="{{ route('admin.index') }}" class="text-muted">{{ __('管理者') }}</a>:
            @foreach ($userStatus['administrators'] as $role => $administratorCount)
                <span class="ms-2" data-role-count="{{ $role }}">{{ $administratorCount['label'] }} {{ number_format($administratorCount['count']) }}</span>
            @endforeach
        </div>
        <div class="small text-muted mb-1">{{ __('最近ログインしたユーザー') }}</div>
        @forelse ($userStatus['recent_logins'] as $login)
            <div class="d-flex align-items-center gap-2 py-1 small">
                <span class="text-muted text-nowrap">{{ $login->created_at?->format('m/d H:i') }}</span>
                <span>{{ $login->actor_name ?? '—' }}</span>
                <span class="badge text-bg-light border">{{ $login->actor_type === 'administrator' ? __('管理者') : __('ユーザー') }}</span>
            </div>
        @empty
            <div class="small text-muted">{{ __('ログインの記録はまだありません。') }}</div>
        @endforelse
    </div>
</div>
